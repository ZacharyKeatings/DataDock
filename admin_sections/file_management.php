<?php
$fileCount = count($allFiles);
$totalSize = array_reduce($allFiles, fn($s, $f) => $s + (int)($f['filesize'] ?? 0), 0);
$fileStatusFilter = $fileStatusFilter ?? 'all';
?>
<div class="admin-file-management">
    <div class="file-mgmt-header">
        <h2 class="page-title">File Management</h2>
        <div class="file-mgmt-summary">
            <span class="file-mgmt-stat"><strong><?= number_format($fileCount) ?></strong> file<?= $fileCount !== 1 ? 's' : '' ?></span>
            <span class="file-mgmt-stat"><strong><?= format_filesize($totalSize) ?></strong> total</span>
            <?php if ($fileStatusFilter === 'pending'): ?>
                <span class="file-mgmt-stat file-mgmt-filter-label">(pending)</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="file-mgmt-toolbar">
        <div class="file-mgmt-filter">
            <span class="file-mgmt-filter-label">Status:</span>
            <a href="admin.php?section=files" class="btn btn-small<?= $fileStatusFilter === 'all' ? ' btn-primary' : '' ?>">All</a>
            <a href="admin.php?section=files&status=pending" class="btn btn-small<?= $fileStatusFilter === 'pending' ? ' btn-primary' : '' ?>">Pending</a>
        </div>
        <div class="file-mgmt-purge-forms" style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <form method="post" class="file-mgmt-purge-form">
            <input type="hidden" name="purge" value="1">
            <button type="submit" class="btn btn-primary">Purge Expired Files</button>
        </form>
        <form method="post" class="file-mgmt-purge-form">
            <input type="hidden" name="purge_trash" value="1">
            <button type="submit" class="btn btn-small">Purge Trash</button>
        </form>
        </div>
    </div>

    <?php if ($allFiles): ?>
    <div class="file-list file-list-admin">
        <div class="file-row-file-management file-header">
            <div>User</div>
            <div>Filename</div>
            <div>Type</div>
            <div>Size</div>
            <div>Status</div>
            <div>Downloads</div>
            <div>Uploaded</div>
            <div>Expires</div>
            <div>Preview</div>
            <div>Actions</div>
        </div>

        <?php foreach ($allFiles as $file): ?>
            <div class="file-row-file-management">
                <div><?= user_profile_link($file['username'] ?? null) ?></div>
                <div class="file-name-cell">
                    <?= render_file_icon(get_file_icon($file['filetype'], $file['original_name'] ?? '')) ?>
                    <span title="<?= sanitize_data($file['original_name']) ?>"><?= sanitize_data($file['original_name']) ?></span>
                    <?php if (!empty($file['mime_anomaly'])): ?>
                        <span class="badge badge-warning" title="Extension vs MIME mismatch – review recommended">MIME?</span>
                    <?php endif; ?>
                </div>
                <div title="<?= sanitize_data($file['filetype']) ?>">
                    <?= sanitize_data(get_friendly_filetype($file['filetype'])) ?>
                </div>
                <div><?= format_filesize($file['filesize']) ?></div>
                <div>
                    <?php
                    $q = $file['quarantine_status'] ?? 'approved';
                    if ($q === 'pending'): ?>
                        <span class="badge badge-pending">Pending</span>
                    <?php else: ?>
                        <span class="badge badge-ok">Approved</span>
                    <?php endif; ?>
                </div>
                <div><?= (int) ($file['download_count'] ?? 0) ?></div>
                <div><span class="utc-datetime" data-utc="<?= sanitize_data($file['upload_date']) ?>"></span></div>
                <div>
                    <?= $file['expiry_date']
                        ? '<span class="utc-datetime" data-utc="' . htmlspecialchars($file['expiry_date']) . '"></span>'
                        : '—' ?>
                </div>
                <div class="file-preview-cell">
                    <?php if ($file['thumbnail_path'] && str_starts_with($file['filetype'], 'image/')): ?>
                        <img src="thumbnail.php?id=<?= (int)$file['id'] ?>" alt="Thumb" class="thumbnail-small">
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
                <div class="file-actions">
                    <?php if (($file['quarantine_status'] ?? '') === 'pending'): ?>
                        <a href="approve_file.php?id=<?= (int)$file['id'] ?>" class="btn btn-small">Approve</a>
                    <?php endif; ?>
                    <a href="download.php?id=<?= $file['id'] ?>" class="btn btn-small">Download</a>
                    <a href="delete.php?id=<?= $file['id'] ?>&from=admin" class="btn btn-small btn-danger" onclick="return confirm('Delete this file?')">Delete</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="file-mgmt-empty">
        <p>No uploaded files found.</p>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
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
