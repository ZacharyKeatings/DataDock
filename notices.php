<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/notices.php';
require_once __DIR__ . '/includes/pagination.php';

$userId = (int) $_SESSION['user_id'];
$noticePager = datadock_page_clamp(datadock_page_request('p', 25), datadock_count_notices($pdo, $userId));
$notices = datadock_list_notices($pdo, $userId, $noticePager['per_page'], $noticePager['offset']);
datadock_mark_notices_read($pdo, $userId);

$pageTitle = 'Notices';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section">
    <h2 class="page-title">Notices</h2>
    <p class="page-description">Shares, report reviews, quota warnings, and files that expire soon. These stay here even when email is not configured.</p>

    <?php if (!$notices): ?>
        <div class="empty-state">
            <p>No notices yet.</p>
        </div>
    <?php else: ?>
        <ul class="notice-list">
            <?php foreach ($notices as $notice): ?>
                <?php $unread = empty($notice['read_at']); ?>
                <li class="notice-item<?= $unread ? ' is-unread' : '' ?>">
                    <p class="notice-title"><?= sanitize_data((string) ($notice['title'] ?? '')) ?></p>
                    <?php if (!empty($notice['body'])): ?>
                        <p class="notice-body"><?= sanitize_data((string) $notice['body']) ?></p>
                    <?php endif; ?>
                    <p class="notice-meta">
                        <span class="utc-datetime" data-utc="<?= sanitize_data((string) ($notice['created_at'] ?? '')) ?>"></span>
                        <?php if (!empty($notice['link_url'])): ?>
                            · <a href="<?= sanitize_data((string) $notice['link_url']) ?>">Open</a>
                        <?php endif; ?>
                    </p>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php datadock_render_pager($noticePager); ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.utc-datetime').forEach(function (el) {
        var utc = el.dataset.utc;
        if (!utc) return;
        el.textContent = new Date(utc + ' UTC').toLocaleString();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
