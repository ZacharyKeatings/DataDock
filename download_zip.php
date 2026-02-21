<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$ids = [];
if (isset($_GET['ids'])) {
    $ids = is_array($_GET['ids']) ? array_map('intval', $_GET['ids']) : array_map('intval', explode(',', $_GET['ids']));
}
$ids = array_filter(array_unique($ids));
if (empty($ids)) {
    $_SESSION['flash_error'][] = "❌ No files selected.";
    header("Location: dashboard.php");
    exit;
}

$userId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

$placeholders = implode(',', array_fill(0, count($ids), '?'));
if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id IN ($placeholders) AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())");
    $stmt->execute($ids);
} else {
    // Owner OR shared with user
    $stmt = $pdo->prepare("
        SELECT f.* FROM files f
        LEFT JOIN file_shares fs ON f.id = fs.file_id AND fs.shared_with_user_id = ?
        WHERE f.id IN ($placeholders) AND (f.quarantine_status = 'approved' OR f.quarantine_status IS NULL)
        AND (f.expiry_date IS NULL OR f.expiry_date > UTC_TIMESTAMP())
        AND (f.user_id = ? OR fs.id IS NOT NULL)
    ");
    $stmt->execute(array_merge([$userId], $ids, [$userId]));
}
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($files)) {
    $_SESSION['flash_error'][] = "❌ No valid files found or permission denied.";
    header("Location: dashboard.php");
    exit;
}

$zipPath = tempnam(sys_get_temp_dir(), 'datadock_zip_');
$zip = new ZipArchive();
if (!$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
    $_SESSION['flash_error'][] = "❌ Could not create zip file.";
    header("Location: dashboard.php");
    exit;
}

foreach ($files as $file) {
    $fullPath = get_upload_path() . $file['filename'];
    if (file_exists($fullPath)) {
        $zip->addFile($fullPath, $file['original_name'] ?: $file['filename']);
    }
}
$zip->close();

$zipName = 'datadock_files_' . date('Y-m-d_His') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
unlink($zipPath);
exit;
