<?php

$pdo = $GLOBALS['datadock_test_pdo'] ?? null;
if (!$pdo instanceof PDO) {
    test_fail('mysql connection', 'test database was not opened');
    return;
}

require_once dirname(__DIR__) . '/includes/notices.php';
require_once dirname(__DIR__) . '/includes/pastes.php';
require_once dirname(__DIR__) . '/includes/quotas.php';
require_once dirname(__DIR__) . '/includes/email_verify.php';
require_once dirname(__DIR__) . '/includes/access_control.php';
require_once dirname(__DIR__) . '/includes/guest_claim.php';
require_once dirname(__DIR__) . '/includes/folder_links.php';
require_once dirname(__DIR__) . '/includes/hotlink_log.php';
require_once dirname(__DIR__) . '/includes/integrity.php';

try {
    run_migrations($pdo);
    test_pass('migrations are safe to repeat');
} catch (Throwable $e) {
    test_fail('migrations are safe to repeat', $e->getMessage());
}

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ([
    'users', 'files', 'pastes', 'folders', 'storage_partitions', 'user_notices',
    'guest_claims', 'signed_links', 'download_quota_events', 'upload_rate_log',
    'activity_log', 'folder_links', 'app_secrets', 'hotlink_log',
] as $table) {
    expect_true('schema includes ' . $table, in_array($table, $tables, true));
}

$userColumns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
foreach (['quota_max_files', 'email_verify_token', 'external_subject', 'suspended', 'storage_partition_id'] as $column) {
    expect_true('users.' . $column . ' exists', in_array($column, $userColumns, true));
}

expect_same(
    'default storage partition is installed',
    (int) $pdo->query('SELECT id FROM storage_partitions WHERE is_default = 1')->fetchColumn(),
    1
);
expect_same('fresh database has no upload names to check', datadock_expected_upload_filenames_by_partition($pdo), []);

$password = password_hash('secret', PASSWORD_DEFAULT);
$insertUser = $pdo->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)');
$insertUser->execute(['ada', 'ada@example.com', $password, 'user']);
$ada = (int) $pdo->lastInsertId();
expect_same('taken username gains a suffix', datadock_unique_username($pdo, 'ada'), 'ada0');
expect_same('empty username becomes user', datadock_unique_username($pdo, '***'), 'user');

$external = datadock_upsert_external_user($pdo, [
    'sub' => 'oidc-subject-1',
    'email' => 'ext@example.com',
    'preferred_username' => 'Ext User',
    'role' => 'user',
], 'oidc');
$again = datadock_upsert_external_user($pdo, [
    'sub' => 'oidc-subject-1',
    'email' => 'ext@example.com',
    'role' => 'admin',
], 'oidc');
expect_same('external login reuses the account', (int) $again['id'], (int) $external['id']);
expect_same('external login can grant admin', $again['role'], 'admin');
$pdo->prepare('UPDATE users SET suspended = 1 WHERE id = ?')->execute([(int) $external['id']]);
$suspended = false;
try {
    datadock_upsert_external_user($pdo, [
        'sub' => 'oidc-subject-1',
        'email' => 'ext@example.com',
        'role' => 'user',
    ], 'oidc');
} catch (RuntimeException $e) {
    $suspended = strpos($e->getMessage(), 'suspended') !== false;
}
expect_true('suspended external account is refused', $suspended);

$pdo->prepare('UPDATE users SET quota_max_files = 1, quota_max_bytes = 1000 WHERE id = ?')->execute([$ada]);
$limits = datadock_user_quota_limits($pdo, $ada, ['user_limits' => ['max_files_enabled' => false]]);
expect_true('per-user file quota overrides the site', $limits['count_enabled']);
expect_same('per-user file quota value', $limits['max_files'], 1);
$insertFile = $pdo->prepare('INSERT INTO files (user_id, filename, original_name, filetype, filesize) VALUES (?, ?, ?, ?, ?)');
$insertFile->execute([$ada, 'note.txt', 'note.txt', 'text/plain', 400]);
$fileId = (int) $pdo->lastInsertId();
$quotaError = datadock_user_quota_error($pdo, $ada, [], 1, 0);
expect_true('file quota blocks another upload', is_string($quotaError) && strpos($quotaError, 'file limit') !== false);
$byteError = datadock_user_quota_error($pdo, $ada, [], 0, 700);
expect_true('byte quota blocks a large upload', is_string($byteError) && strpos($byteError, 'storage limit') !== false);

expect_same(
    'upload rate limit allows the first file',
    check_upload_rate_limit($pdo, $ada, '203.0.113.9', [
        'rate_limit_uploads' => ['enabled' => true, 'window_minutes' => 10, 'max_per_ip' => 1, 'max_per_user' => 1],
    ]),
    true
);
record_upload_rate_limit($pdo, '203.0.113.9', $ada, 1);
expect_false(
    'upload rate limit blocks the next file',
    check_upload_rate_limit($pdo, $ada, '203.0.113.9', [
        'rate_limit_uploads' => ['enabled' => true, 'window_minutes' => 10, 'max_per_ip' => 1, 'max_per_user' => 1],
    ])
);

datadock_add_notice($pdo, $ada, 'welcome!', 'Hello Ada', 'Body', 'dashboard.php');
expect_same('notice is unread', datadock_notice_unread_count($pdo, $ada), 1);
datadock_add_notice_daily($pdo, $ada, 'expiring_soon', '1 file expires within 7 days');
datadock_add_notice_daily($pdo, $ada, 'expiring_soon', '1 file expires within 7 days');
expect_same('daily notice is stored once', datadock_count_notices($pdo, $ada), 2);
datadock_mark_notices_read($pdo, $ada);
expect_same('marking notices clears the unread count', datadock_notice_unread_count($pdo, $ada), 0);
$listed = datadock_list_notices($pdo, $ada, 10, 0);
expect_same('notice list returns both rows', count($listed), 2);
expect_true('notice kind is normalized', in_array('welcome', array_column($listed, 'kind'), true));

$paste = datadock_paste_create($pdo, [
    'user_id' => $ada,
    'guest_id' => null,
    'title' => 'Note',
    'content' => 'hello',
    'expires_at' => null,
    'burn_after_read' => 0,
]);
expect_true('paste token is 32 hex characters', datadock_paste_token_ok($paste['token']));
$opened = datadock_paste_open($pdo, $paste['token']);
expect_same('opening a paste counts a view', (int) ($opened['view_count'] ?? 0), 1);
$burn = datadock_paste_create($pdo, [
    'user_id' => $ada,
    'guest_id' => null,
    'title' => 'Once',
    'content' => 'gone',
    'expires_at' => null,
    'burn_after_read' => 1,
]);
$burned = datadock_paste_open($pdo, $burn['token']);
expect_true('burn-after-read reports itself', !empty($burned['_burned']));
expect_same('burned paste cannot be opened again', datadock_paste_by_token($pdo, $burn['token']), null);

$token = datadock_issue_email_verification($pdo, $ada);
expect_same('verification token length', strlen($token), 64);
expect_true('verification token confirms the address', datadock_confirm_email_token($pdo, $token));
expect_false('verification token cannot be reused', datadock_confirm_email_token($pdo, $token));
expect_same(
    'confirmed account is marked verified',
    (int) $pdo->query('SELECT email_verified FROM users WHERE id = ' . $ada)->fetchColumn(),
    1
);

$expires = time() + 3600;
$link = datadock_signed_link_create($pdo, $fileId, $ada, $expires);
expect_true('signed link is stored', is_array($link));
expect_true(
    'signed link verifies',
    is_array($link) && datadock_signed_download_verify($pdo, $fileId, $expires, $link['sig'], $link['id'])
);
expect_false('expired signature is rejected', datadock_signed_download_verify($pdo, $fileId, time() - 30, 'abcd', null));

$guest = bin2hex(random_bytes(16));
$claim = datadock_issue_guest_claim($pdo, $guest);
expect_true('guest claim code is issued', is_string($claim) && strlen($claim) === 32);
expect_same('open guest claim is reused', datadock_issue_guest_claim($pdo, $guest), $claim);
$claimed = datadock_claim_guest_uploads($pdo, [], $ada, (string) $claim);
expect_true('empty guest claim succeeds', $claimed['ok']);
expect_same('empty guest claim moves no files', $claimed['files'], 0);
$reuse = datadock_claim_guest_uploads($pdo, [], $ada, (string) $claim);
expect_false('used guest claim is rejected', $reuse['ok']);

$pdo->prepare('INSERT INTO folders (user_id, parent_id, name) VALUES (?, 0, ?)')->execute([$ada, 'Docs']);
$docs = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO folders (user_id, parent_id, name) VALUES (?, ?, ?)')->execute([$ada, $docs, 'Nested']);
$nested = (int) $pdo->lastInsertId();
expect_true('nested folder is under its parent', datadock_folder_is_under($pdo, $nested, $docs, $ada));
expect_false('folder is not under a stranger', datadock_folder_is_under($pdo, $nested, $docs, $ada + 99));

$folderToken = str_repeat('ab', 32);
$pdo->prepare('INSERT INTO folder_links (folder_id, user_id, token) VALUES (?, ?, ?)')->execute([$docs, $ada, $folderToken]);
$folderLink = datadock_folder_link_by_token($pdo, $folderToken);
expect_same('folder link resolves the folder name', $folderLink['folder_name'] ?? null, 'Docs');
$pdo->prepare('UPDATE folder_links SET expires_at = ? WHERE token = ?')->execute(['2000-01-01 00:00:00', $folderToken]);
expect_same('expired folder link is hidden', datadock_folder_link_by_token($pdo, $folderToken), null);

$_SERVER['REMOTE_ADDR'] = '203.0.113.20';
$_SESSION['user_id'] = $ada;
$quotaSettings = ['download_quota' => [
    'enabled' => true,
    'window_minutes' => 60,
    'max_bytes_per_user' => 100,
    'max_bytes_per_file' => 0,
]];
expect_same('download under the quota is allowed', datadock_download_quota_check($pdo, ['id' => $fileId, 'filesize' => 80], $quotaSettings), null);
$blocked = datadock_download_quota_check($pdo, ['id' => $fileId, 'filesize' => 80], $quotaSettings);
expect_true('download over the quota is blocked', is_string($blocked) && strpos((string) $blocked, 'Download limit') !== false);

datadock_log_activity($pdo, 'test action', [
    'actor_user_id' => $ada,
    'file_id' => $fileId,
    'detail' => ['kind' => 'test'],
    'ip' => '127.0.0.1',
]);
$action = $pdo->query("SELECT action, detail_json FROM activity_log WHERE action = 'test_action'")->fetch(PDO::FETCH_ASSOC);
expect_same('activity action is normalized', $action['action'] ?? null, 'test_action');
expect_contains('activity detail is stored', (string) ($action['detail_json'] ?? ''), 'test');

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTP_REFERER'] = 'https://evil.example/pic';
datadock_log_hotlink_if_external($pdo, ['hotlink_logging_enabled' => true], 'thumbnail', $fileId, $ada);
$hotlinks = (int) $pdo->query("SELECT COUNT(*) FROM hotlink_log WHERE referer_host = 'evil.example'")->fetchColumn();
expect_same('external referer is logged', $hotlinks, 1);

require_once dirname(__DIR__) . '/includes/accounts.php';
require_once dirname(__DIR__) . '/includes/user_trust.php';
require_once dirname(__DIR__) . '/includes/backup_export.php';
require_once dirname(__DIR__) . '/includes/purge_ops.php';
require_once dirname(__DIR__) . '/includes/ops_alerts.php';
require_once dirname(__DIR__) . '/includes/storage.php';

expect_same('active admin count ignores suspended admins', datadock_count_admins($pdo, true), 0);
expect_true('total admin count includes suspended admins', datadock_count_admins($pdo, false) >= 1);
expect_false('user without a totp secret does not need totp', datadock_user_needs_totp($pdo, $ada));
expect_same('storage byte total matches the file', datadock_user_total_storage_bytes($pdo, $ada), 400);
datadock_maybe_record_storage_snapshot($pdo, $ada);
datadock_maybe_record_storage_snapshot($pdo, $ada);
expect_same('storage snapshot is recorded once an hour', (int) $pdo->query('SELECT COUNT(*) FROM user_storage_snapshots WHERE user_id = ' . $ada)->fetchColumn(), 1);
datadock_log_file_download_event($pdo, $fileId, '203.0.113.50');
expect_same('download event is stored', (int) $pdo->query('SELECT COUNT(*) FROM file_download_events WHERE file_id = ' . $fileId)->fetchColumn(), 1);

expect_same('sql null is unquoted', datadock_sql_quote($pdo, null), 'NULL');
expect_contains('sql export includes the user insert', datadock_export_database_sql($pdo), 'INSERT INTO `users`');
expect_contains('file metadata export includes the original name', datadock_export_files_metadata_json($pdo), 'note.txt');

expect_same('guest partition is the default', datadock_resolve_user_partition_id($pdo, null), 1);
expect_same('user without a partition uses the default', datadock_resolve_user_partition_id($pdo, $ada), 1);
$sha = str_repeat('ab', 32);
expect_same('short checksum is not a duplicate', datadock_find_duplicate_object($pdo, 1, 'abc'), null);
$objectId = datadock_create_storage_object($pdo, 1, $sha, 'object.bin', 12);
$found = datadock_find_duplicate_object($pdo, 1, $sha);
expect_same('stored object can be found by checksum', (int) ($found['id'] ?? 0), $objectId);
datadock_increment_object_ref($pdo, $objectId);
expect_same('object ref count increments', (int) $pdo->query('SELECT ref_count FROM storage_objects WHERE id = ' . $objectId)->fetchColumn(), 2);
expect_contains('file path uses the stored filename', datadock_file_main_path($pdo, ['storage_partition_id' => 1, 'filename' => 'note.txt']), 'note.txt');
expect_same('file without a thumbnail has no thumb path', datadock_file_thumb_path($pdo, ['storage_partition_id' => 1]), null);

datadock_sync_file_tags($pdo, $fileId, $ada, 'notes, notes, archive');
expect_same('duplicate tag names collapse', (int) $pdo->query('SELECT COUNT(*) FROM file_tags WHERE file_id = ' . $fileId)->fetchColumn(), 2);

$owned = datadock_owned_pastes($pdo, 20, 0);
expect_true('owned pastes include the saved note', count(array_filter($owned, static function ($row) {
    return ($row['title'] ?? '') === 'Note';
})) === 1);

$pdo->prepare('INSERT INTO pastes (user_id, token, content, expires_at) VALUES (?, ?, ?, ?)')->execute([$ada, str_repeat('cd', 16), 'old', '2000-01-01 00:00:00']);
expect_true('expired pastes are purged', datadock_purge_expired_pastes($pdo) >= 1);
$pdo->prepare('INSERT INTO share_folders (user_id, token, expires_at) VALUES (?, ?, ?)')->execute([$ada, str_repeat('ef', 32), '2000-01-01 00:00:00']);
expect_true('expired share folders are purged', datadock_purge_expired_share_folders($pdo) >= 1);

$pdo->prepare('INSERT INTO files (user_id, filename, original_name, filetype, filesize, expiry_date, purge_hold) VALUES (?, ?, ?, ?, ?, ?, 0)')->execute([
    $ada, 'expired-missing.bin', 'expired-missing.bin', 'text/plain', 20, '2000-01-01 00:00:00',
]);
$purged = datadock_purge_expired_files($pdo);
expect_same('expired file row is deleted', $purged['deleted'], 1);
expect_same('expired file bytes are counted', $purged['freed_bytes'], 20);

$scan = datadock_scan_uploads_vs_database($pdo);
expect_true('files missing on disk are reported', count($scan['missing']) >= 1);
$verify = datadock_verify_file_checksums($pdo, 5);
expect_true('checksum verify reports unreadable files', count($verify['errors']) >= 1);

$alerts = datadock_collect_ops_alerts($pdo, [
    'ops_alerts' => ['user_quota_percent_enabled' => true, 'user_quota_percent_threshold' => 1],
    'user_limits' => ['max_files_enabled' => true, 'max_files' => 1],
]);
expect_true('quota alert fires when the cap is reached', count(array_filter($alerts, static function ($alert) {
    return ($alert['type'] ?? '') === 'quota_files';
})) >= 1);

datadock_refresh_account_notices($pdo, $ada, ['ops_alerts' => ['user_quota_percent_threshold' => 50]], 2, 80, true);
expect_true('expiring files create a notice', (int) $pdo->query("SELECT COUNT(*) FROM user_notices WHERE user_id = {$ada} AND kind = 'expiring_soon'")->fetchColumn() >= 1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
datadock_session_record($pdo, $ada);
expect_same('session row is stored', (int) $pdo->query('SELECT COUNT(*) FROM user_sessions WHERE user_id = ' . $ada)->fetchColumn(), 1);
$begin = datadock_begin_email_verification($pdo, $ada, 'ada@example.com');
expect_same('verification email is not sent without smtp', $begin['sent'], false);
expect_contains('verification begin returns a url', $begin['url'], 'verify_email.php?token=');
