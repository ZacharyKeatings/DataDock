<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Your Files";
require_once __DIR__ . '/includes/header.php';

$userId = $_SESSION['user_id'];

// Fetch user's files
$stmt = $pdo->prepare("SELECT * FROM files 
    WHERE user_id = ? 
    AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP()) 
    ORDER BY upload_date DESC");
$stmt->execute([$userId]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-section">
    <h2 class="page-title">Your Uploaded Files</h2>

    <?php if ($files): ?>
        <form method="get" action="download_zip.php" id="zipForm" style="margin-bottom:1rem;">
            <button type="submit" class="btn btn-small" id="zipBtn" disabled>Download selected as ZIP</button>
        </form>
        <div class="file-list">
            <div class="file-row-dashboard file-header">
                <div><input type="checkbox" id="selectAll" title="Select all"></div>
                <div>Filename</div>
                <div>Type</div>
                <div>Size</div>
                <div>Downloads</div>
                <div>Uploaded</div>
                <div>Expires</div>
                <div>Thumbnail</div>
                <div>Checksums</div>
                <div>Actions</div>
            </div>

            <?php foreach ($files as $file): ?>
                <?php $fileIcon = get_file_icon($file['filetype'], $file['original_name'] ?? ''); ?>
                <div class="file-row-dashboard">
                    <div><input type="checkbox" form="zipForm" name="ids[]" value="<?= $file['id'] ?>" class="zip-checkbox"></div>
                    <div><span class="file-icon"><?= (str_starts_with($fileIcon, 'http') ? '<img src="' . sanitize_data($fileIcon) . '" alt="" class="file-icon-img">' : $fileIcon) ?></span> <?= sanitize_data($file['original_name']) ?></div>
                    <div title="<?= sanitize_data($file['filetype']) ?>">
                        <?= sanitize_data(get_friendly_filetype($file['filetype'])) ?>
                    </div>
                    <div><?= number_format($file['filesize'] / 1024, 2) ?> KB</div>
                    <div><?= (int) ($file['download_count'] ?? 0) ?></div>
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
                    <div class="checksum-cell">
                        <?php if (!empty($file['checksum_md5']) || !empty($file['checksum_sha256'])): ?>
                            <?php if (!empty($file['checksum_md5'])): ?>
                            <span title="MD5: <?= sanitize_data($file['checksum_md5']) ?>"><small>MD5</small></span>
                            <button type="button" class="btn-copy" data-copy="<?= sanitize_data($file['checksum_md5']) ?>" title="Copy MD5">📋</button>
                            <?php endif; ?>
                            <?php if (!empty($file['checksum_sha256'])): ?>
                            <span title="SHA256: <?= sanitize_data($file['checksum_sha256']) ?>"><small>SHA256</small></span>
                            <button type="button" class="btn-copy" data-copy="<?= sanitize_data($file['checksum_sha256']) ?>" title="Copy SHA256">📋</button>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                    <div class="file-actions">
                        <a href="download.php?id=<?= $file['id'] ?>" class="btn btn-small">Download</a>
                        <a href="create_onetime.php?id=<?= $file['id'] ?>" class="btn btn-small">One-time link</a>
                        <a href="delete.php?id=<?= $file['id'] ?>" class="btn btn-small btn-danger" onclick="return confirm('Delete this file?')">Delete</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>You haven't uploaded any files yet.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.utc-datetime').forEach(el => {
        const utc = el.dataset.utc;
        if (utc) {
            const local = new Date(utc + ' UTC');
            el.textContent = local.toLocaleString();
        } else {
            el.textContent = '—';
        }
    });
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.zip-checkbox');
    const zipBtn = document.getElementById('zipBtn');
    if (selectAll && checkboxes.length) {
        selectAll.addEventListener('change', () => {
            checkboxes.forEach(cb => { cb.checked = selectAll.checked; });
            zipBtn.disabled = !selectAll.checked;
        });
    }
    checkboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            zipBtn.disabled = !document.querySelectorAll('.zip-checkbox:checked').length;
        });
    });
    document.querySelectorAll('.btn-copy').forEach(btn => {
        btn.addEventListener('click', () => {
            const val = btn.dataset.copy;
            if (val && navigator.clipboard) navigator.clipboard.writeText(val);
            const orig = btn.textContent;
            btn.textContent = '✓';
            setTimeout(() => { btn.textContent = orig; }, 1500);
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
