<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/personal.php';

$userId = (int) $_SESSION['user_id'];
$zipPath = tempnam(sys_get_temp_dir(), 'datadock_export_');
if ($zipPath === false) {
    $_SESSION['flash_error'][] = 'Could not prepare the export.';
    header('Location: activity.php');
    exit;
}
$built = datadock_build_user_export_zip($pdo, $userId, $zipPath);
if (!$built['ok']) {
    @unlink($zipPath);
    $_SESSION['flash_error'][] = $built['error'] ?? 'Could not export your files.';
    header('Location: activity.php');
    exit;
}

$name = 'datadock-files-' . gmdate('Y-m-d') . '-' . $userId . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . (string) filesize($zipPath));
header('Cache-Control: no-store');
readfile($zipPath);
unlink($zipPath);
exit;
