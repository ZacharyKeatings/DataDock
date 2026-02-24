<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_admin();
require_once __DIR__ . '/includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_error'][] = "❌ Invalid file.";
    header("Location: admin.php?section=files");
    exit;
}
$fileId = (int) $_GET['id'];

$stmt = $pdo->prepare("UPDATE files SET quarantine_status = 'approved' WHERE id = ? AND deleted_at IS NULL AND (quarantine_status = 'pending' OR quarantine_status IS NULL)");
$stmt->execute([$fileId]);
if ($stmt->rowCount() > 0) {
    $_SESSION['flash_success'][] = "✅ File approved; it is now visible to the owner and shared users.";
} else {
    $_SESSION['flash_warning'][] = "⚠️ File not found or already approved.";
}
header("Location: admin.php?section=files");
exit;
