<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/email_verify.php';

$token = trim((string) ($_GET['token'] ?? ''));
if (datadock_confirm_email_token($pdo, $token)) {
    $_SESSION['flash_success'][] = 'Email confirmed. You can sign in.';
} else {
    $_SESSION['flash_error'][] = 'That verification link is invalid or has expired.';
}
header('Location: login.php');
exit;
