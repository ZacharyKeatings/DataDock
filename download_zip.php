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
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id IN ($placeholders) AND user_id = ? AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())");
    $stmt->execute(array_merge($ids, [$userId]));
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
    $fullPath = __DIR__ . '/uploads/' . $file['filename'];
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
