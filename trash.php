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
            <div class="file-row-dashboard file-header">
                <div>Filename</div>
                <div>Type</div>
                <div>Size</div>
                <div>Deleted</div>
                <div>Actions</div>
            </div>

            <?php foreach ($files as $file): ?>
                <div class="file-row-dashboard">
                    <div>
                        <?= render_file_icon(get_file_icon($file['filetype'], $file['original_name'] ?? '')) ?>
                        <?= sanitize_data($file['original_name']) ?>
                    </div>
                    <div title="<?= sanitize_data($file['filetype']) ?>">
                        <?= sanitize_data(get_friendly_filetype($file['filetype'])) ?>
                    </div>
                    <div><?= number_format($file['filesize'] / 1024, 2) ?> KB</div>
                    <div><span class="utc-datetime" data-utc="<?= sanitize_data($file['deleted_at']) ?>"></span></div>
                    <div class="file-actions">
                        <?php if (!$readOnly): ?>
                        <a href="restore_file.php?id=<?= (int)$file['id'] ?>" class="btn btn-small">Restore</a>
                        <a href="delete.php?id=<?= (int)$file['id'] ?>&permanent=1&from=trash" class="btn btn-small btn-danger" onclick="return confirm('Permanently delete this file? This cannot be undone.')">Delete permanently</a>
                        <?php else: ?>
                        <span class="settings-hint">Read-only mode</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Trash is empty.</p>
    <?php endif; ?>

    <p style="margin-top:1.5rem;"><a href="dashboard.php" class="btn btn-small">← Back to Your Files</a></p>
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
