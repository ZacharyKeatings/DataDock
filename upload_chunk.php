<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
require_once __DIR__ . '/includes/read_only.php';
require_once __DIR__ . '/includes/ingest_file.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/rate_limit.php';

header('Content-Type: application/json; charset=UTF-8');

function datadock_chunk_json(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if (datadock_read_only_enabled($settings)) {
    datadock_chunk_json(['ok' => false, 'error' => 'Uploads are disabled (read-only / archive mode).'], 403);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !datadock_csrf_is_valid()) {
    datadock_chunk_json(['ok' => false, 'error' => 'That upload could not be confirmed. Refresh the page and try again.'], 400);
}

$guestUploadsEnabled = !empty($settings['guest_uploads']['enabled']);
$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$isGuest = $currentUserId < 1 && $guestUploadsEnabled;
if ($currentUserId < 1 && !$isGuest) {
    datadock_chunk_json(['ok' => false, 'error' => 'Log in to upload.'], 403);
}
$guestId = '';
if ($isGuest) {
    $guestId = (string) ($_COOKIE['guest_id'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $guestId)) {
        $guestId = bin2hex(random_bytes(16));
        setcookie('guest_id', $guestId, time() + 86400 * 30, '/');
    }
}
$ownerKey = $currentUserId > 0 ? ('u' . $currentUserId) : ('g' . $guestId);
$root = rtrim(sys_get_temp_dir(), '/') . '/datadock-chunks';
if (!is_dir($root)) {
    @mkdir($root, 0700, true);
}

$action = (string) ($_POST['action'] ?? '');

if ($action === 'start') {
    $name = (string) ($_POST['filename'] ?? 'upload');
    $size = (int) ($_POST['size'] ?? 0);
    $max = (int) ($settings['max_file_size'] ?? 0);
    if ($size < 1 || ($max > 0 && $size > $max)) {
        datadock_chunk_json(['ok' => false, 'error' => 'That file is empty or larger than the site limit.'], 400);
    }
    $id = bin2hex(random_bytes(16));
    $dir = $root . '/' . $id;
    if (!@mkdir($dir, 0700, true)) {
        datadock_chunk_json(['ok' => false, 'error' => 'Could not start the upload.'], 500);
    }
    file_put_contents($dir . '/meta.json', json_encode([
        'owner' => $ownerKey,
        'name' => $name,
        'size' => $size,
        'next' => 0,
        'created' => time(),
    ]));
    datadock_chunk_json(['ok' => true, 'upload_id' => $id]);
}

$uploadId = (string) ($_POST['upload_id'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
    datadock_chunk_json(['ok' => false, 'error' => 'Unknown upload.'], 400);
}
$dir = $root . '/' . $uploadId;
$metaPath = $dir . '/meta.json';
if (!is_file($metaPath)) {
    datadock_chunk_json(['ok' => false, 'error' => 'That upload expired. Start again.'], 404);
}
$meta = json_decode((string) file_get_contents($metaPath), true);
if (!is_array($meta) || ($meta['owner'] ?? '') !== $ownerKey) {
    datadock_chunk_json(['ok' => false, 'error' => 'That upload does not belong to this session.'], 403);
}

if ($action === 'chunk') {
    $index = (int) ($_POST['index'] ?? -1);
    if ($index !== (int) ($meta['next'] ?? 0)) {
        datadock_chunk_json(['ok' => false, 'error' => 'Chunks arrived out of order.'], 400);
    }
    if (empty($_FILES['chunk']['tmp_name']) || !is_uploaded_file($_FILES['chunk']['tmp_name'])) {
        datadock_chunk_json(['ok' => false, 'error' => 'Missing chunk.'], 400);
    }
    $dest = $dir . '/part_' . $index;
    if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $dest)) {
        datadock_chunk_json(['ok' => false, 'error' => 'Could not store that chunk.'], 500);
    }
    $meta['next'] = $index + 1;
    file_put_contents($metaPath, json_encode($meta));
    datadock_chunk_json(['ok' => true]);
}

if ($action === 'finish') {
    $assembled = $dir . '/assembled';
    $out = fopen($assembled, 'wb');
    if (!$out) {
        datadock_chunk_json(['ok' => false, 'error' => 'Could not assemble the file.'], 500);
    }
    $next = (int) ($meta['next'] ?? 0);
    for ($i = 0; $i < $next; $i++) {
        $part = $dir . '/part_' . $i;
        if (!is_file($part)) {
            fclose($out);
            datadock_chunk_json(['ok' => false, 'error' => 'A chunk is missing.'], 400);
        }
        $in = fopen($part, 'rb');
        if ($in) {
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
    }
    fclose($out);
    $size = (int) filesize($assembled);
    if ($size !== (int) ($meta['size'] ?? -1)) {
        datadock_chunk_json(['ok' => false, 'error' => 'The assembled file size does not match.'], 400);
    }
    $durations = [
        '1_minute' => '+1 minute',
        '30_minutes' => '+30 minutes',
        '1_hour' => '+1 hour',
        '6_hours' => '+6 hours',
        '1_day' => '+1 day',
        '1_week' => '+1 week',
        '1_month' => '+1 month',
        '1_year' => '+1 year',
        'never' => null,
    ];
    $chosen = (string) ($_POST['duration'] ?? 'never');
    $expiry = null;
    if (isset($durations[$chosen]) && $durations[$chosen] !== null) {
        $expiry = (new DateTime('now', new DateTimeZone('UTC')))->modify($durations[$chosen])->format('Y-m-d H:i:s');
    }
    $clientIp = get_client_ip();
    if (!check_upload_rate_limit($pdo, $currentUserId > 0 ? $currentUserId : null, $clientIp, $settings)) {
        datadock_chunk_json(['ok' => false, 'error' => 'Too many uploads. Please try again in a few minutes.'], 429);
    }
    $stored = datadock_ingest_local_file($pdo, $settings, [
        'source_path' => $assembled,
        'original_name' => (string) ($meta['name'] ?? 'upload'),
        'file_size' => $size,
        'user_id' => $currentUserId,
        'guest_id' => $isGuest ? $guestId : null,
        'folder_id' => (int) ($_POST['folder_id'] ?? 0),
        'is_public' => isset($_POST['is_public']) ? 1 : 0,
        'expiry_ts' => $expiry,
        'relocate' => 'rename',
    ]);
    foreach (glob($dir . '/part_*') ?: [] as $part) {
        @unlink($part);
    }
    @unlink($metaPath);
    @rmdir($dir);
    if (!$stored['ok']) {
        datadock_chunk_json(['ok' => false, 'error' => $stored['error']], 400);
    }
    record_upload_rate_limit($pdo, $clientIp, $currentUserId > 0 ? $currentUserId : null, 1);
    datadock_log_activity($pdo, 'upload', [
        'actor_user_id' => $currentUserId > 0 ? $currentUserId : null,
        'actor_guest_id' => $isGuest ? $guestId : null,
        'file_id' => (int) $stored['file_id'],
        'detail' => ['name' => (string) ($meta['name'] ?? ''), 'size' => $size, 'chunked' => true],
    ]);
    $message = 'Uploaded ' . (string) ($meta['name'] ?? 'file') . '.';
    if ($isGuest) {
        require_once __DIR__ . '/includes/guest_claim.php';
        $claim = datadock_issue_guest_claim($pdo, $guestId);
        if ($claim) {
            $message .= ' Claim code: ' . $claim . '.';
        }
    }
    if (($stored['quarantine'] ?? '') === 'pending') {
        $message .= ' It is waiting for review.';
    }
    datadock_chunk_json(['ok' => true, 'message' => $message, 'file_id' => (int) $stored['file_id']]);
}

datadock_chunk_json(['ok' => false, 'error' => 'Unknown upload action.'], 400);
