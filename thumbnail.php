<?php
/**
 * Serve thumbnail images. Used when thumbnails may be stored outside the web root
 * (e.g. with custom storage_base_path). Looks up file by ID and streams the image.
 */
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/includes/hotlink_log.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit;
}

$fileId = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND deleted_at IS NULL AND thumbnail_path IS NOT NULL AND thumbnail_path != ''");
$stmt->execute([$fileId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit;
}

$path = datadock_file_thumb_path($pdo, $row);
if ($path === null) {
    http_response_code(404);
    exit;
}
if (!file_exists($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

datadock_log_hotlink_if_external($pdo, $settings, 'thumbnail', $fileId);
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=86400');
readfile($path);
exit;
