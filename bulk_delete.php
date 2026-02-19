<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ids']) || !is_array($_POST['ids'])) {
    $_SESSION['flash_error'][] = "❌ No files selected.";
    header("Location: dashboard.php");
    exit;
}

$ids = array_filter(array_map('intval', $_POST['ids']));
if (empty($ids)) {
    $_SESSION['flash_error'][] = "❌ No valid files selected.";
    header("Location: dashboard.php");
    exit;
}

$userId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

$placeholders = implode(',', array_fill(0, count($ids), '?'));
if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT id, filename, thumbnail_path, original_name FROM files WHERE id IN ($placeholders) AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())");
    $stmt->execute($ids);
} else {
    $stmt = $pdo->prepare("SELECT id, filename, thumbnail_path, original_name FROM files WHERE id IN ($placeholders) AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP()) AND user_id = ?");
    $stmt->execute(array_merge($ids, [$userId]));
}
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

$deleted = 0;
$uploadPath = get_upload_path();
$thumbPath = get_thumbnails_path();

foreach ($files as $file) {
    $fullPath = $uploadPath . $file['filename'];
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
    if (!empty($file['thumbnail_path']) && file_exists($thumbPath . $file['thumbnail_path'])) {
        unlink($thumbPath . $file['thumbnail_path']);
    }
    if ($isAdmin) {
        $pdo->prepare("DELETE FROM files WHERE id = ?")->execute([$file['id']]);
    } else {
        $pdo->prepare("DELETE FROM files WHERE id = ? AND user_id = ?")->execute([$file['id'], $userId]);
    }
    $deleted++;
}

$_SESSION['flash_success'][] = "✅ $deleted file(s) deleted.";
header("Location: dashboard.php");
exit;
