<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
$readOnly = datadock_read_only_enabled($settings);

$pageTitle = "Trash";
require_once __DIR__ . '/includes/header.php';

$userId = $_SESSION['user_id'];
[$where, $params] = build_files_list_where([
    'user_id' => $userId,
    'trashed_only' => true,
    'exclude_trashed' => false,
]);
$stmt = $pdo->prepare("SELECT * FROM files f WHERE $where ORDER BY f.deleted_at DESC");
$stmt->execute($params);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

$trashRetentionDays = (int) ($settings['trash_retention_days'] ?? 30);
?>

<div class="page-section">
    <h2 class="page-title">Trash</h2>
    <p class="page-description">Deleted files are kept here for <?= $trashRetentionDays > 0 ? $trashRetentionDays . ' days' : 'until you empty trash' ?>. Restore or permanently delete them.</p>

    <?php if ($files): ?>
        <div class="file-list">
            <div class="file-row-dashboard file-header file-row-trash">
                <div>Filename</div>
                <div>Type</div>
                <div>Size</div>
                <div>Deleted</div>
                <div>Actions</div>
            </div>

            <?php foreach ($files as $file): ?>
                <div class="file-row-dashboard file-row-trash">
                    <div class="file-name-cell">
                        <?= render_file_icon(get_file_icon($file['filetype'], $file['original_name'] ?? '')) ?>
                        <span><?= sanitize_data($file['original_name']) ?></span>
                    </div>
                    <div title="<?= sanitize_data($file['filetype']) ?>">
                        <?= sanitize_data(get_friendly_filetype($file['filetype'])) ?>
                    </div>
                    <div><?= format_filesize($file['filesize']) ?></div>
                    <div><span class="utc-datetime" data-utc="<?= sanitize_data($file['deleted_at']) ?>"></span></div>
                    <div class="file-actions">
                        <?php if (!$readOnly): ?>
                        <form method="post" action="restore_file.php" class="inline-action">
                            <?= datadock_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $file['id'] ?>">
                            <button type="submit" class="btn btn-quiet btn-small">Restore</button>
                        </form>
                        <form method="post" action="delete.php" class="inline-action" onsubmit="return confirm('Permanently delete this file? This cannot be undone.')">
                            <?= datadock_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $file['id'] ?>">
                            <input type="hidden" name="permanent" value="1">
                            <input type="hidden" name="from" value="trash">
                            <button type="submit" class="btn btn-small btn-danger">Delete permanently</button>
                        </form>
                        <?php else: ?>
                        <span class="settings-hint">Read-only mode</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>Trash is empty.</p>
        </div>
    <?php endif; ?>

    <p class="empty-state"><a href="dashboard.php" class="btn btn-quiet">Back to your files</a></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.utc-datetime').forEach(el => {
        const utc = el.dataset.utc;
        if (utc) {
            const local = new Date(utc + ' UTC');
            const d = document.createElement('span');
            d.className = 'datetime-date';
            d.textContent = local.toLocaleDateString();
            const t = document.createElement('span');
            t.className = 'datetime-time';
            t.textContent = local.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
            el.textContent = '';
            el.appendChild(d);
            el.appendChild(t);
        } else {
            el.textContent = '—';
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
