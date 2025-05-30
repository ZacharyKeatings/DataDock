<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php'; // Needed for sanitize_data()

// Validate file ID parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit('Invalid request.');
}

$fileId = (int) $_GET['id'];
$userId = $_SESSION['user_id'];

// Fetch file and verify ownership
$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
$stmt->execute([$fileId, $userId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    $_SESSION['flash_error'][] = "❌ File not found or permission denied.";
    header("Location: dashboard.php");
    exit;
}

// Delete physical file
$uploadPath = __DIR__ . '/uploads/' . $file['filename'];
if (file_exists($uploadPath)) {
    unlink($uploadPath);
}

// Delete thumbnail if exists
if (!empty($file['thumbnail_path'])) {
    $thumbPath = __DIR__ . '/thumbnails/' . $file['thumbnail_path'];
    if (file_exists($thumbPath)) {
        unlink($thumbPath);
    }
}

// Delete DB record
$stmt = $pdo->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
$stmt->execute([$fileId, $userId]);

$_SESSION['flash_success'][] = [
    'html' => true,
    'msg' => "✅ <code>" . sanitize_data($file['original_name']) . "</code> deleted successfully."
];
header("Location: dashboard.php");
exit;
