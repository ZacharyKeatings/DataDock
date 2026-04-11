<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT id, username, email, display_name, role, created_at, storage_partition_id FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    $_SESSION['flash_error'][] = 'Account not found.';
    header('Location: dashboard.php');
    exit;
}
unset($user['password_hash'], $user['password']);

$stmt = $pdo->prepare("
    SELECT id, original_name, filename, description, filetype, filesize, upload_date, expiry_date,
           download_count, is_public, checksum_md5, checksum_sha256, folder_id, storage_partition_id,
           quarantine_status, mime_anomaly, deleted_at
    FROM files
    WHERE user_id = ?
    ORDER BY id ASC
");
$stmt->execute([$userId]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT id, created_at, action, file_id, related_user_id, detail_json, ip_address
    FROM activity_log
    WHERE actor_user_id = ?
    ORDER BY created_at DESC
    LIMIT 5000
");
$stmt->execute([$userId]);
$activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT e.id, e.file_id, e.downloaded_at, e.ip_address, e.country_code
    FROM file_download_events e
    INNER JOIN files f ON f.id = e.file_id AND f.user_id = ?
    ORDER BY e.downloaded_at DESC
    LIMIT 5000
");
$stmt->execute([$userId]);
$downloadEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$export = [
    'export_version' => 1,
    'generated_at_utc' => gmdate('c'),
    'site' => get_site_name(),
    'account' => $user,
    'files' => $files,
    'activity_log_self' => $activity,
    'download_events_for_own_files' => $downloadEvents,
];

$json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    $_SESSION['flash_error'][] = 'Export failed.';
    header('Location: activity.php');
    exit;
}

$fn = 'datadock-export-' . gmdate('Y-m-d') . '-' . $userId . '.json';
header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $fn) . '"');
header('Cache-Control: no-store');
echo $json;
exit;
