<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
require_once __DIR__ . '/includes/read_only.php';
require_once __DIR__ . '/includes/personal.php';

$settings = datadock_load_settings();
$fileId = (int) ($_POST['file_id'] ?? 0);
$versionId = (int) ($_POST['version_id'] ?? 0);
$back = 'edit_file.php?id=' . $fileId;
datadock_block_if_read_only($settings, $back, $back);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . $back);
    exit;
}
datadock_reject_invalid_csrf($back);
$result = datadock_restore_file_version($pdo, $fileId, $versionId, (int) $_SESSION['user_id']);
if (!$result['ok']) {
    $_SESSION['flash_error'][] = $result['error'] ?? 'Could not restore that version.';
} else {
    $_SESSION['flash_success'][] = 'The previous copy is current again. The bytes it replaced are kept as a version.';
}
header('Location: ' . $back);
exit;
