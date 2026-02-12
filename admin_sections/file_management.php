<h2 class="page-title">File Management</h2>

<form method="post" class="form" style="margin-bottom: 2rem;">
    <input type="hidden" name="purge" value="1">
    <button type="submit" class="btn btn-primary">Purge Expired Files</button>
</form>

<h3 class="page-subtitle">All Uploaded Files</h3>

<?php if ($allFiles): ?>
    <div class="file-list">
        <div class="file-row-file-management file-header">
            <div>User</div>
            <div>Filename</div>
            <div>Type</div>
            <div>Size</div>
            <div>Uploaded</div>
            <div>Expires</div>
            <div>Preview</div>
            <div>Actions</div>
        </div>

        <?php foreach ($allFiles as $file): ?>
            <div class="file-row-file-management">
                <div><?= sanitize_data($file['username'] ?? 'Guest') ?></div>
                <div><?= sanitize_data($file['original_name']) ?></div>
                <div title="<?= sanitize_data($file['filetype']) ?>">
                    <?= sanitize_data(get_friendly_filetype($file['filetype'])) ?>
                </div>
                <div><?= format_filesize($file['filesize']) ?></div>
                <div><span class="utc-datetime" data-utc="<?= sanitize_data($file['upload_date']) ?>"></span></div>
                <div>
                    <?= $file['expiry_date']
                        ? '<span class="utc-datetime" data-utc="' . htmlspecialchars($file['expiry_date']) . '"></span>'
                        : 'Never' ?>
                </div>
                <div>
                    <?php if ($file['thumbnail_path'] && str_starts_with($file['filetype'], 'image/')): ?>
                        <img src="thumbnails/<?= sanitize_data($file['thumbnail_path']) ?>" alt="Thumb" class="thumbnail-small">
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
                <div class="file-actions">
                    <a href="download.php?id=<?= $file['id'] ?>" class="btn btn-small">Download</a> |
                    <a href="delete.php?id=<?= $file['id'] ?>&from=admin" class="btn btn-small btn-danger" onclick="return confirm('Delete this file?')">Delete</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>No uploaded files found.</p>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.utc-datetime').forEach(el => {
        const utc = el.dataset.utc;
        if (utc) {
            const local = new Date(utc + ' UTC');
            el.textContent = local.toLocaleString();
        } else {
            el.textContent = '—';
        }
    });
});
</script>
