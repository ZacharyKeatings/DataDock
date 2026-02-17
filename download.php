<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/settings.php';

$userId = $_SESSION['user_id'] ?? null;
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$publicBrowsingEnabled = !empty($settings['public_browsing_enabled']);

// One-time download via token (no login required)
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);
    $stmt = $pdo->prepare("SELECT f.* FROM files f JOIN download_tokens dt ON f.id = dt.file_id WHERE dt.token = ?");
    $stmt->execute([$token]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($file) {
        $pdo->prepare("DELETE FROM download_tokens WHERE token = ?")->execute([$token]);
        $path = get_upload_path() . $file['filename'];
        if (file_exists($path)) {
            $pdo->prepare("UPDATE files SET download_count = COALESCE(download_count, 0) + 1 WHERE id = ?")->execute([$file['id']]);
            header('Content-Description: File Transfer');
            header('Content-Type: ' . ($file['filetype'] ?? 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }
    }
    if (empty($userId)) {
        header("Location: login.php");
        exit;
    }
    $_SESSION['flash_error'][] = "❌ Download link expired or invalid.";
    header("Location: dashboard.php");
    exit;
}

// Anonymous download of public files (when public browsing is enabled)
if (empty($userId) && isset($_GET['id']) && is_numeric($_GET['id']) && $publicBrowsingEnabled) {
    $fileId = (int) $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND is_public = 1 AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($file) {
        $path = get_upload_path() . $file['filename'];
        if (file_exists($path)) {
            $pdo->prepare("UPDATE files SET download_count = COALESCE(download_count, 0) + 1 WHERE id = ?")->execute([$fileId]);
            header('Content-Description: File Transfer');
            header('Content-Type: ' . ($file['filetype'] ?? 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }
    }
}

require_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_error'][] = "❌ Invalid download request.";
    header("Location: dashboard.php");
    exit;
}

$fileId = (int) $_GET['id'];

if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
} else {
    // Owner OR shared with this user
    $stmt = $pdo->prepare("
        SELECT f.* FROM files f
        LEFT JOIN file_shares fs ON f.id = fs.file_id AND fs.shared_with_user_id = ?
        WHERE f.id = ? AND (f.user_id = ? OR fs.id IS NOT NULL)
    ");
    $stmt->execute([$userId, $fileId, $userId]);
}
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    $_SESSION['flash_error'][] = "❌ File not found or permission denied.";
    header("Location: dashboard.php");
    exit;
}

$path = get_upload_path() . $file['filename'];
if (!file_exists($path)) {
    $_SESSION['flash_error'][] = "❌ File is missing from server.";
    header("Location: dashboard.php");
    exit;
}

$pdo->prepare("UPDATE files SET download_count = COALESCE(download_count, 0) + 1 WHERE id = ?")->execute([$fileId]);

header('Content-Description: File Transfer');
header('Content-Type: ' . ($file['filetype'] ?? 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
