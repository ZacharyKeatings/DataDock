<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
require_once __DIR__ . '/includes/hotlink_log.php';
require_once __DIR__ . '/includes/audit_log.php';

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
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id IN ($placeholders) AND deleted_at IS NULL AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())");
    $stmt->execute($ids);
} else {
    // Owner OR shared with user; exclude trashed
    $stmt = $pdo->prepare("
        SELECT f.* FROM files f
        LEFT JOIN file_shares fs ON f.id = fs.file_id AND fs.shared_with_user_id = ?
        WHERE f.id IN ($placeholders) AND f.deleted_at IS NULL AND (f.quarantine_status = 'approved' OR f.quarantine_status IS NULL)
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
    $fullPath = datadock_file_main_path($pdo, $file);
    if (file_exists($fullPath)) {
        $zip->addFile($fullPath, $file['original_name'] ?: $file['filename']);
    }
}
$zip->close();

datadock_log_hotlink_if_external($pdo, $settings, 'download_zip');
datadock_log_activity($pdo, 'download_zip', [
    'actor_user_id' => $userId,
    'detail' => [
        'count' => count($files),
        'ids' => array_map(static function ($f) {
            return (int) ($f['id'] ?? 0);
        }, $files),
    ],
]);
$zipName = 'datadock_files_' . date('Y-m-d_His') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
unlink($zipPath);
exit;
