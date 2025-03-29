<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/config/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit('Invalid request.');
}

$fileId = (int) $_GET['id'];
$userId = $_SESSION['user_id'];

// Get file info from DB and ensure it belongs to the logged-in user
$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
$stmt->execute([$fileId, $userId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    http_response_code(404);
    exit('File not found or permission denied.');
}

$path = __DIR__ . '/uploads/' . $file['filename'];
if (!file_exists($path)) {
    http_response_code(410);
    exit('File is missing from server.');
}

// Set headers and serve the file
header('Content-Description: File Transfer');
header('Content-Type: ' . $file['filetype']);
header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
