<?php
require_once __DIR__ . '/../includes/auth.php';
init_session();
require_admin();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/updater.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ../admin.php?section=updater');
    exit;
}
datadock_reject_invalid_csrf('../admin.php?section=updater');
if (datadock_docker_image_mode()) {
    $_SESSION['flash_error'][] = 'This copy is a Docker image. Pull a new image instead of installing a zip.';
    header('Location: ../admin.php?section=updater');
    exit;
}

$upload = $_FILES['release_zip'] ?? null;
if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $_SESSION['flash_error'][] = 'Choose the release zip you downloaded.';
    header('Location: ../admin.php?section=updater');
    exit;
}
$name = (string) ($upload['name'] ?? '');
if (!str_ends_with(strtolower($name), '.zip')) {
    $_SESSION['flash_error'][] = 'The update file must be a .zip release.';
    header('Location: ../admin.php?section=updater');
    exit;
}
$tmp = (string) ($upload['tmp_name'] ?? '');
$held = sys_get_temp_dir() . '/datadock-upload-' . bin2hex(random_bytes(4)) . '.zip';
if (!@move_uploaded_file($tmp, $held)) {
    $_SESSION['flash_error'][] = 'Could not read the uploaded zip.';
    header('Location: ../admin.php?section=updater');
    exit;
}
$result = datadock_install_release_zip($held, dirname(__DIR__));
@unlink($held);
if (!$result['ok']) {
    $_SESSION['flash_error'][] = $result['error'] ?? 'The release zip could not be installed.';
} else {
    $_SESSION['flash_success'][] = 'Installed ' . $result['label'] . '. Settings, uploads, and the database were left in place.';
}
header('Location: ../admin.php?section=updater');
exit;
