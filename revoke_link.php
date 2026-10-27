<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
require_once __DIR__ . '/includes/read_only.php';
datadock_block_if_read_only($settings);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$type = (string) ($_POST['type'] ?? '');
$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0 || !in_array($type, ['token', 'signed'], true)) {
    $_SESSION['flash_error'][] = '❌ Invalid link.';
    header('Location: dashboard.php');
    exit;
}

if ($type === 'token') {
    if ($isAdmin) {
        $stmt = $pdo->prepare('
            SELECT dt.id, dt.file_id FROM download_tokens dt
            JOIN files f ON f.id = dt.file_id
            WHERE dt.id = ? AND f.deleted_at IS NULL
        ');
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare('
            SELECT dt.id, dt.file_id FROM download_tokens dt
            JOIN files f ON f.id = dt.file_id
            WHERE dt.id = ? AND f.user_id = ? AND f.deleted_at IS NULL
        ');
        $stmt->execute([$id, $userId]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $_SESSION['flash_error'][] = '❌ Link not found.';
    } else {
        $pdo->prepare('DELETE FROM download_tokens WHERE id = ?')->execute([$id]);
        datadock_log_activity($pdo, 'share_link_revoked', [
            'actor_user_id' => $userId,
            'file_id' => (int) $row['file_id'],
            'detail' => ['token_id' => $id],
        ]);
        $_SESSION['flash_success'][] = '✅ Share link revoked.';
    }
} else {
    if ($isAdmin) {
        $stmt = $pdo->prepare('SELECT id, file_id FROM signed_links WHERE id = ? AND revoked_at IS NULL');
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare('SELECT id, file_id FROM signed_links WHERE id = ? AND user_id = ? AND revoked_at IS NULL');
        $stmt->execute([$id, $userId]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $_SESSION['flash_error'][] = '❌ Link not found.';
    } else {
        $pdo->prepare('UPDATE signed_links SET revoked_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
        datadock_log_activity($pdo, 'signed_link_revoked', [
            'actor_user_id' => $userId,
            'file_id' => (int) $row['file_id'],
            'detail' => ['signed_link_id' => $id],
        ]);
        $_SESSION['flash_success'][] = '✅ Signed link revoked.';
    }
}

header('Location: dashboard.php');
exit;
