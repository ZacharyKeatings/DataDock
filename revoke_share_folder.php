<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/audit_log.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'][] = '❌ Invalid share.';
    header('Location: dashboard.php');
    exit;
}

if ($isAdmin) {
    $stmt = $pdo->prepare('SELECT id FROM share_folders WHERE id = ?');
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare('SELECT id FROM share_folders WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
}
if (!$stmt->fetch()) {
    $_SESSION['flash_error'][] = '❌ Share not found or permission denied.';
    header('Location: dashboard.php');
    exit;
}

$pdo->prepare('DELETE FROM share_folders WHERE id = ?')->execute([$id]);
datadock_log_activity($pdo, 'share_folder_revoked', [
    'actor_user_id' => $userId,
    'detail' => ['share_folder_id' => $id],
]);
$_SESSION['flash_success'][] = '✅ Share folder link revoked.';
header('Location: dashboard.php');
exit;
