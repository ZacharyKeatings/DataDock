<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_admin();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
require_once __DIR__ . '/includes/read_only.php';
datadock_block_if_read_only($settings);
require_once __DIR__ . '/includes/audit_log.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_error'][] = "❌ Invalid file.";
    header("Location: admin.php?section=files");
    exit;
}
$fileId = (int) $_GET['id'];

$stmt = $pdo->prepare("UPDATE files SET quarantine_status = 'approved' WHERE id = ? AND deleted_at IS NULL AND (quarantine_status = 'pending' OR quarantine_status IS NULL)");
$stmt->execute([$fileId]);
if ($stmt->rowCount() > 0) {
    $st2 = $pdo->prepare('SELECT original_name, filename FROM files WHERE id = ?');
    $st2->execute([$fileId]);
    $fn = $st2->fetch(PDO::FETCH_ASSOC);
    datadock_log_activity($pdo, 'admin_approve_file', [
        'actor_user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'file_id' => $fileId,
        'detail' => ['name' => $fn['original_name'] ?? $fn['filename'] ?? ''],
    ]);
    $_SESSION['flash_success'][] = "✅ File approved; it is now visible to the owner and shared users.";
} else {
    $_SESSION['flash_warning'][] = "⚠️ File not found or already approved.";
}
header("Location: admin.php?section=files");
exit;
