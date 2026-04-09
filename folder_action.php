<?php
/**
 * Legacy entry point: folder creation is handled on dashboard.php (same-document POST).
 * If something still POSTs here, forward into the dashboard handler.
 */
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/dashboard_actions.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['name'])) {
    $_POST['datadock_folder_create'] = '1';
}
datadock_process_dashboard_post($pdo);
header('Location: dashboard.php');
exit;
