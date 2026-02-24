<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_error'][] = "❌ Invalid request.";
    header("Location: trash.php");
    exit;
}

$fileId = (int) $_GET['id'];
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, original_name FROM files WHERE id = ? AND user_id = ? AND deleted_at IS NOT NULL");
$stmt->execute([$fileId, $userId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    $_SESSION['flash_error'][] = "❌ File not found or not in trash.";
    header("Location: trash.php");
    exit;
}

$pdo->prepare("UPDATE files SET deleted_at = NULL WHERE id = ? AND user_id = ?")->execute([$fileId, $userId]);

$_SESSION['flash_success'][] = [
    'html' => true,
    'msg' => "✅ <code>" . sanitize_data($file['original_name']) . "</code> restored."
];
header("Location: dashboard.php");
exit;
