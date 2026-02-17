<?php
/**
 * Serve thumbnail images. Used when thumbnails may be stored outside the web root
 * (e.g. with custom storage_base_path). Looks up file by ID and streams the image.
 */
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit;
}

$fileId = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT thumbnail_path, filetype FROM files WHERE id = ? AND thumbnail_path IS NOT NULL AND thumbnail_path != ''");
$stmt->execute([$fileId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit;
}

$path = get_thumbnails_path() . $row['thumbnail_path'];
if (!file_exists($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=86400');
readfile($path);
exit;
