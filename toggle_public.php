<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/settings.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !is_numeric($_POST['id'])) {
    header("Location: dashboard.php");
    exit;
}

$fileId = (int) $_POST['id'];
$userId = $_SESSION['user_id'];
$publicBrowsingEnabled = !empty($settings['public_browsing_enabled']);

if (!$publicBrowsingEnabled) {
    $_SESSION['flash_error'][] = "❌ Public browsing is not enabled.";
    header("Location: dashboard.php");
    exit;
}

$stmt = $pdo->prepare("SELECT id, is_public FROM files WHERE id = ? AND user_id = ? AND deleted_at IS NULL");
$stmt->execute([$fileId, $userId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    $_SESSION['flash_error'][] = "❌ File not found or permission denied.";
    header("Location: dashboard.php");
    exit;
}

$newPublic = $file['is_public'] ? 0 : 1;
$pdo->prepare("UPDATE files SET is_public = ? WHERE id = ?")->execute([$newPublic, $fileId]);
$_SESSION['flash_success'][] = $newPublic ? "✅ File is now public." : "✅ File is now private.";
header("Location: dashboard.php");
exit;
