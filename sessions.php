<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pagination.php';

$userId = (int) $_SESSION['user_id'];
$current = datadock_session_hash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    datadock_reject_invalid_csrf('sessions.php');
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'revoke_others') {
        $pdo->prepare('UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND session_hash != ? AND revoked_at IS NULL')->execute([$userId, $current]);
        $_SESSION['flash_success'][] = 'Other sessions were signed out.';
    } elseif ($action === 'revoke') {
        $id = (int) ($_POST['session_id'] ?? 0);
        $pdo->prepare('UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ? AND session_hash != ?')->execute([$id, $userId, $current]);
        $_SESSION['flash_success'][] = 'That session was signed out.';
    }
    header('Location: sessions.php');
    exit;
}

$sessionCount = $pdo->prepare('SELECT COUNT(*) FROM user_sessions WHERE user_id = ?');
$sessionCount->execute([$userId]);
$sessionPager = datadock_page_clamp(datadock_page_request('p', 25), (int) $sessionCount->fetchColumn());
$stmt = $pdo->prepare('SELECT id, session_hash, ip_address, user_agent, created_at, last_seen, revoked_at FROM user_sessions WHERE user_id = ? ORDER BY last_seen DESC LIMIT ' . (int) $sessionPager['per_page'] . ' OFFSET ' . (int) $sessionPager['offset']);
$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Sessions';
require __DIR__ . '/includes/header.php';
?>
<div class="page-section">
    <h2 class="page-title">Sessions</h2>
    <p class="page-description">These are the browsers currently signed in to your account. Revoking one signs that browser out the next time it talks to the site.</p>
    <form method="post">
        <?= datadock_csrf_field() ?>
        <input type="hidden" name="action" value="revoke_others">
        <button type="submit" class="btn btn-quiet">Sign out other sessions</button>
    </form>
    <?php if (!$rows): ?>
        <div class="empty-state"><p>No sessions are recorded yet. This browser will appear after the next page load.</p></div>
    <?php else: ?>
        <div class="notice-list">
            <?php foreach ($rows as $row): ?>
                <div class="notice-item">
                    <strong><?= hash_equals($current, (string) $row['session_hash']) ? 'This device' : 'Other device' ?></strong>
                    <p><?= sanitize_data($row['user_agent'] ?: 'Unknown browser') ?></p>
                    <p class="form-hint"><?= sanitize_data((string) ($row['ip_address'] ?? '')) ?> · last seen <?= sanitize_data((string) $row['last_seen']) ?><?= !empty($row['revoked_at']) ? ' · signed out' : '' ?></p>
                    <?php if (empty($row['revoked_at']) && !hash_equals($current, (string) $row['session_hash'])): ?>
                    <form method="post">
                        <?= datadock_csrf_field() ?>
                        <input type="hidden" name="action" value="revoke">
                        <input type="hidden" name="session_id" value="<?= (int) $row['id'] ?>">
                        <button type="submit" class="btn btn-quiet btn-small">Revoke</button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php datadock_render_pager($sessionPager); ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
