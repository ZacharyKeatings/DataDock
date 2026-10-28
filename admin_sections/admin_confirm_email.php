<?php
require_once __DIR__ . '/../includes/auth.php';
init_session();
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin.php?section=users');
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);
if ($userId > 0) {
    $pdo->prepare('UPDATE users SET email_verified = 1, email_verify_token = NULL, email_verify_expires = NULL WHERE id = ?')
        ->execute([$userId]);
    $_SESSION['flash_success'][] = 'Email marked as confirmed.';
}
header('Location: ../admin.php?section=users');
exit;
