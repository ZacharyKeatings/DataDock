<?php
/**
 * Legacy entry point: moves are handled on dashboard.php (same-document POST).
 */
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/dashboard_actions.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['file_id'])) {
    $_POST['datadock_move_file'] = '1';
}
datadock_process_dashboard_post($pdo);
header('Location: dashboard.php');
exit;
