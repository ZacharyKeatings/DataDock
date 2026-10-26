<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php'; // Needed for sanitize_data()
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
require_once __DIR__ . '/includes/read_only.php';
datadock_block_if_read_only($settings);
require_once __DIR__ . '/includes/audit_log.php';

$from = (string) ($_POST['from'] ?? '');
if (!in_array($from, ['trash', 'admin'], true)) {
    $from = '';
}
$redirect = $from === 'trash' ? 'trash.php' : ($from === 'admin' ? 'admin.php?section=files' : 'dashboard.php');
datadock_reject_invalid_csrf($redirect);

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    $_SESSION['flash_error'][] = '❌ Invalid request.';
    header('Location: ' . $redirect);
    exit;
}

$fileId = (int) $_POST['id'];
$userId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// Fetch file and verify ownership (admins can access any file)
if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
    $stmt->execute([$fileId, $userId]);
}
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    $_SESSION['flash_error'][] = "❌ File not found or permission denied.";
    header("Location: dashboard.php");
    exit;
}

$permanent = isset($_POST['permanent']) && $_POST['permanent'] === '1';

if ($permanent) {
    // Permanent delete: remove file from disk and DB (e.g. from trash)
    datadock_release_file_storage($pdo, $file);
    if ($isAdmin) {
        $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
        $stmt->execute([$fileId]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute([$fileId, $userId]);
    }
    datadock_log_activity($pdo, 'delete_permanent', [
        'actor_user_id' => $userId,
        'file_id' => $fileId,
        'detail' => ['name' => $file['original_name'] ?? '', 'admin' => $isAdmin],
    ]);
    $_SESSION['flash_success'][] = [
        'html' => true,
        'msg' => "✅ <code>" . sanitize_data($file['original_name']) . "</code> permanently deleted."
    ];
} else {
    // Soft delete: move to trash (set deleted_at)
    if ($isAdmin) {
        $stmt = $pdo->prepare("UPDATE files SET deleted_at = UTC_TIMESTAMP() WHERE id = ?");
        $stmt->execute([$fileId]);
    } else {
        $stmt = $pdo->prepare("UPDATE files SET deleted_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ?");
        $stmt->execute([$fileId, $userId]);
    }
    datadock_log_activity($pdo, 'delete_trash', [
        'actor_user_id' => $userId,
        'file_id' => $fileId,
        'detail' => ['name' => $file['original_name'] ?? '', 'admin' => $isAdmin],
    ]);
    $_SESSION['flash_success'][] = [
        'html' => true,
        'msg' => "✅ <code>" . sanitize_data($file['original_name']) . "</code> moved to trash."
    ];
}

header("Location: $redirect");
exit;
