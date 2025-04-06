<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/config/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_error'][] = "❌ Invalid download request.";
    header("Location: dashboard.php");
    exit;
}

$fileId = (int) $_GET['id'];
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
$stmt->execute([$fileId, $userId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    $_SESSION['flash_error'][] = "❌ File not found or permission denied.";
    header("Location: dashboard.php");
    exit;
}

$path = __DIR__ . '/uploads/' . $file['filename'];
if (!file_exists($path)) {
    $_SESSION['flash_error'][] = "❌ File is missing from server.";
    header("Location: dashboard.php");
    exit;
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $file['filetype']);
header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
