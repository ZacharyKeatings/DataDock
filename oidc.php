<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();

if (isset($_GET['code'], $_GET['state'])) {
    datadock_oidc_finish($pdo, $settings, (string) $_GET['code'], (string) $_GET['state']);
}
if (!empty($_GET['error'])) {
    $_SESSION['flash_error'][] = 'The identity provider did not sign you in.';
    header('Location: login.php');
    exit;
}
datadock_oidc_start($settings);
