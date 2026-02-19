<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/settings.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ids'], $_POST['public']) || !is_array($_POST['ids'])) {
    $_SESSION['flash_error'][] = "❌ Invalid request.";
    header("Location: dashboard.php");
    exit;
}

$publicBrowsingEnabled = !empty($settings['public_browsing_enabled']);
if (!$publicBrowsingEnabled) {
    $_SESSION['flash_error'][] = "❌ Public browsing is not enabled.";
    header("Location: dashboard.php");
    exit;
}

$makePublic = (int) $_POST['public'] === 1;
$ids = array_filter(array_map('intval', $_POST['ids']));
if (empty($ids)) {
    $_SESSION['flash_error'][] = "❌ No files selected.";
    header("Location: dashboard.php");
    exit;
}

$userId = $_SESSION['user_id'];
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT id FROM files WHERE id IN ($placeholders) AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP()) AND user_id = ?");
$stmt->execute(array_merge($ids, [$userId]));
$owned = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($owned)) {
    $_SESSION['flash_error'][] = "❌ No valid files found or permission denied.";
    header("Location: dashboard.php");
    exit;
}

$placeholdersOwned = implode(',', array_fill(0, count($owned), '?'));
$pdo->prepare("UPDATE files SET is_public = ? WHERE id IN ($placeholdersOwned)")->execute(array_merge([$makePublic ? 1 : 0], $owned));
$count = count($owned);
$_SESSION['flash_success'][] = $makePublic ? "✅ $count file(s) are now public." : "✅ $count file(s) are now private.";
header("Location: dashboard.php");
exit;
