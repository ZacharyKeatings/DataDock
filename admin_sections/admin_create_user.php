<?php
require_once __DIR__ . '/../includes/auth.php';
init_session();
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/settings_loader.php';
require_once __DIR__ . '/../includes/accounts.php';
$settings = datadock_load_settings();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin.php?section=users');
    exit;
}

$username = trim((string) ($_POST['username'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$role = (($_POST['role'] ?? '') === 'admin') ? 'admin' : 'user';

if (strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
    $_SESSION['flash_error'][] = 'Username must be 3–50 characters and use only letters, numbers, dots, underscores, or hyphens.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'][] = 'Enter a valid email address.';
} else {
    $passwordError = datadock_password_error($password, $settings);
    if ($passwordError !== null) {
        $_SESSION['flash_error'][] = $passwordError;
    }
}

if (empty($_SESSION['flash_error'])) {
    $exists = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
    $exists->execute([$username, $email]);
    if ($exists->fetch()) {
        $_SESSION['flash_error'][] = 'That username or email is already in use.';
    } else {
        $pdo->prepare('INSERT INTO users (username, email, password_hash, role, auth_source) VALUES (?, ?, ?, ?, ?)')->execute([
            $username,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $role,
            'local',
        ]);
        $_SESSION['flash_success'][] = 'Account created for ' . $username . '.';
    }
}

header('Location: ../admin.php?section=users');
exit;
