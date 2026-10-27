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
$mode = (string) ($_POST['quota_mode'] ?? 'inherit');
if (!in_array($mode, ['inherit', 'unlimited', 'custom'], true)) {
    $mode = 'inherit';
}
$stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    $_SESSION['flash_error'][] = 'That account was not found.';
    header('Location: ../admin.php?section=users');
    exit;
}

$files = null;
$bytes = null;
if ($mode === 'unlimited') {
    $files = -1;
    $bytes = -1;
} elseif ($mode === 'custom') {
    $files = (int) ($_POST['max_files'] ?? 0);
    if ($files < 0) {
        $files = 0;
    }
    $bytes = form_size_to_bytes($_POST['max_bytes'] ?? 0, (string) ($_POST['max_bytes_unit'] ?? 'm'));
    if ($bytes < 0) {
        $bytes = 0;
    }
}

$pdo->prepare('UPDATE users SET quota_max_files = ?, quota_max_bytes = ? WHERE id = ?')->execute([$files, $bytes, $userId]);
$_SESSION['flash_success'][] = 'Storage quota updated for ' . $user['username'] . '.';
header('Location: ../admin.php?section=users');
exit;
