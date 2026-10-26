<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
require_once __DIR__ . '/includes/read_only.php';
require_once __DIR__ . '/includes/pastes.php';

$returnTo = (string) ($_POST['return_to'] ?? '');
if (!in_array($returnTo, ['dashboard.php', 'paste.php'], true)) {
    $returnTo = !empty($_SESSION['user_id']) ? 'dashboard.php' : 'paste.php';
}

datadock_block_if_read_only($settings, $returnTo, 'paste.php');
datadock_reject_invalid_csrf($returnTo);

$id = (int) ($_POST['id'] ?? 0);
if ($id < 1) {
    $_SESSION['flash_error'][] = '❌ Invalid paste.';
    header('Location: ' . $returnTo);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM pastes WHERE id = ?');
$stmt->execute([$id]);
$paste = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$paste || !datadock_paste_can_delete($paste)) {
    $_SESSION['flash_error'][] = '❌ Paste not found or permission denied.';
    header('Location: ' . $returnTo);
    exit;
}

$pdo->prepare('DELETE FROM pastes WHERE id = ?')->execute([$id]);
datadock_log_activity($pdo, 'paste_deleted', [
    'actor_user_id' => !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
    'actor_guest_id' => empty($_SESSION['user_id']) ? datadock_paste_guest_id(false) : null,
    'detail' => [
        'paste_id' => $id,
        'title' => (string) ($paste['title'] ?? ''),
    ],
]);
$_SESSION['flash_success'][] = '✅ Paste deleted.';
header('Location: ' . $returnTo);
exit;
