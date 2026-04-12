<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
require_once __DIR__ . '/includes/read_only.php';
datadock_block_if_read_only($settings);
require_once __DIR__ . '/includes/audit_log.php';

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
    $stmt = $pdo->prepare("SELECT id FROM files WHERE id IN ($placeholders) AND deleted_at IS NULL");
    $stmt->execute($ids);
} else {
    $stmt = $pdo->prepare("SELECT id FROM files WHERE id IN ($placeholders) AND deleted_at IS NULL AND user_id = ?");
    $stmt->execute(array_merge($ids, [$userId]));
}
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

$deleted = 0;
foreach ($files as $file) {
    if ($isAdmin) {
        $pdo->prepare("UPDATE files SET deleted_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$file['id']]);
    } else {
        $pdo->prepare("UPDATE files SET deleted_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ?")->execute([$file['id'], $userId]);
    }
    $deleted++;
}

datadock_log_activity($pdo, 'bulk_delete_trash', [
    'actor_user_id' => $userId,
    'detail' => ['count' => $deleted, 'ids' => array_column($files, 'id'), 'admin' => $isAdmin],
]);

$_SESSION['flash_success'][] = "✅ $deleted file(s) moved to trash.";
header("Location: dashboard.php");
exit;
