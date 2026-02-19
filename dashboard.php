<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/settings.php';

$pageTitle = "Your Files";
require_once __DIR__ . '/includes/header.php';

$userId = $_SESSION['user_id'];
$publicBrowsingEnabled = !empty($settings['public_browsing_enabled']);

// Fetch user's files
$stmt = $pdo->prepare("SELECT * FROM files 
    WHERE user_id = ? 
    AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP()) 
    ORDER BY upload_date DESC");
$stmt->execute([$userId]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch files shared with user
$stmt = $pdo->prepare("
    SELECT f.*, u.username as shared_by_username
    FROM files f
    JOIN file_shares fs ON f.id = fs.file_id
    JOIN users u ON fs.shared_by_user_id = u.id
    WHERE fs.shared_with_user_id = ?
    AND (f.expiry_date IS NULL OR f.expiry_date > UTC_TIMESTAMP())
    ORDER BY f.upload_date DESC
");
$stmt->execute([$userId]);
$sharedFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-section">
    <h2 class="page-title">Your Uploaded Files</h2>

    <?php if ($files): ?>
        <div class="bulk-actions-bar" style="margin-bottom:1rem;">
            <label for="bulkActionSelect" class="bulk-label">Bulk actions:</label>
            <select id="bulkActionSelect" class="bulk-select">
                <option value="">Choose action…</option>
                <option value="zip">Download selected as ZIP</option>
                <?php if ($publicBrowsingEnabled): ?>
                <option value="public">Make selected public</option>
                <option value="private">Make selected private</option>
                <?php endif; ?>
                <option value="delete">Delete selected</option>
            </select>
            <button type="button" class="btn btn-small" id="bulkApplyBtn" disabled>Apply</button>
        </div>
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
                <div>Actions</div>
            </div>

            <?php foreach ($files as $file): ?>
                <div class="file-row-dashboard-wrapper">
                    <div class="file-row-dashboard">
                        <div><input type="checkbox" name="ids[]" value="<?= $file['id'] ?>" class="zip-checkbox"></div>
                        <div><?= render_file_icon(get_file_icon($file['filetype'], $file['original_name'] ?? '')) ?> <?= sanitize_data($file['original_name']) ?></div>
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
                                <img src="thumbnail.php?id=<?= (int)$file['id'] ?>" alt="Thumb" class="thumbnail-small">
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                        <div class="file-actions">
                            <details class="file-actions-dropdown">
                                <summary class="btn btn-small">Actions <span class="dropdown-arrow" aria-hidden="true">▾</span></summary>
                                <div class="dropdown-menu">
                                    <?php if ($publicBrowsingEnabled): ?>
                                    <form method="post" action="toggle_public.php" class="dropdown-item-form">
                                        <input type="hidden" name="id" value="<?= $file['id'] ?>">
                                        <button type="submit" class="dropdown-item" title="<?= $file['is_public'] ? 'Make private' : 'Make public' ?>">
                                            <span class="btn-icon"><?= icon_svg($file['is_public'] ? 'lock-open' : 'lock') ?></span> <?= $file['is_public'] ? 'Public' : 'Private' ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <a href="share.php?id=<?= $file['id'] ?>" class="dropdown-item">Share</a>
                                    <a href="download.php?id=<?= $file['id'] ?>" class="dropdown-item">Download</a>
                                    <a href="create_onetime.php?id=<?= $file['id'] ?>" class="dropdown-item">One-time link</a>
                                    <a href="delete.php?id=<?= $file['id'] ?>" class="dropdown-item dropdown-item-danger" onclick="return confirm('Delete this file?')">Delete</a>
                                </div>
                            </details>
                        </div>
                    </div>
                    <div class="file-row-checksums">
                        <?php if (!empty($file['checksum_md5']) || !empty($file['checksum_sha256'])): ?>
                            <?php if (!empty($file['checksum_md5'])): ?>
                            <div class="checksum-item">
                                <span class="checksum-line">
                                    <button type="button" class="hash-label" data-hash="<?= sanitize_data($file['checksum_md5']) ?>" data-algo="MD5">MD5</button>
                                    <button type="button" class="btn-copy btn-copy-checksum" data-copy="<?= sanitize_data($file['checksum_md5']) ?>" title="Copy MD5"><?= icon_svg('copy') ?></button>
                                </span>
                                <code class="hash-value" aria-live="polite"></code>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($file['checksum_sha256'])): ?>
                            <div class="checksum-item">
                                <span class="checksum-line">
                                    <button type="button" class="hash-label" data-hash="<?= sanitize_data($file['checksum_sha256']) ?>" data-algo="SHA256">SHA256</button>
                                    <button type="button" class="btn-copy btn-copy-checksum" data-copy="<?= sanitize_data($file['checksum_sha256']) ?>" title="Copy SHA256"><?= icon_svg('copy') ?></button>
                                </span>
                                <code class="hash-value" aria-live="polite"></code>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="checksum-none">—</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>You haven't uploaded any files yet.</p>
    <?php endif; ?>
</div>

<?php if (!empty($sharedFiles)): ?>
<div class="page-section" style="margin-top:2rem;">
    <h2 class="page-title">Shared with You</h2>
    <div class="file-list">
        <div class="file-row-dashboard file-header">
            <div>Filename</div>
            <div>Shared by</div>
            <div>Type</div>
            <div>Size</div>
            <div>Actions</div>
        </div>
        <?php foreach ($sharedFiles as $file): ?>
            <div class="file-row-dashboard">
                <div><?= render_file_icon(get_file_icon($file['filetype'], $file['original_name'] ?? '')) ?> <?= sanitize_data($file['original_name']) ?></div>
                <div><?= sanitize_data($file['shared_by_username'] ?? '?') ?></div>
                <div><?= sanitize_data(get_friendly_filetype($file['filetype'])) ?></div>
                <div><?= number_format($file['filesize'] / 1024, 2) ?> KB</div>
                <div class="file-actions">
                    <details class="file-actions-dropdown">
                        <summary class="btn btn-small">Actions <span class="dropdown-arrow" aria-hidden="true">▾</span></summary>
                        <div class="dropdown-menu">
                            <a href="download.php?id=<?= $file['id'] ?>" class="dropdown-item">Download</a>
                        </div>
                    </details>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

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
    var selectAll = document.getElementById('selectAll');
    var checkboxes = document.querySelectorAll('.zip-checkbox');
    var bulkSelect = document.getElementById('bulkActionSelect');
    var bulkApplyBtn = document.getElementById('bulkApplyBtn');
    function updateBulkState() {
        var n = document.querySelectorAll('.zip-checkbox:checked').length;
        bulkApplyBtn.disabled = n === 0 || !bulkSelect.value;
    }
    if (selectAll && checkboxes.length) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(function (cb) { cb.checked = selectAll.checked; });
            updateBulkState();
        });
    }
    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', updateBulkState);
    });
    if (bulkSelect) bulkSelect.addEventListener('change', updateBulkState);
    if (bulkApplyBtn) {
        bulkApplyBtn.addEventListener('click', function () {
            var ids = Array.from(document.querySelectorAll('.zip-checkbox:checked')).map(function (c) { return c.value; });
            if (ids.length === 0) return;
            var action = bulkSelect.value;
            if (action === 'zip') {
                window.location.href = 'download_zip.php?' + ids.map(function (id) { return 'ids[]=' + encodeURIComponent(id); }).join('&');
            } else if (action === 'public' || action === 'private') {
                var form = document.createElement('form');
                form.method = 'post';
                form.action = 'bulk_toggle_public.php';
                var pubInput = document.createElement('input');
                pubInput.type = 'hidden';
                pubInput.name = 'public';
                pubInput.value = action === 'public' ? '1' : '0';
                form.appendChild(pubInput);
                ids.forEach(function (id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
            } else if (action === 'delete' && confirm('Delete ' + ids.length + ' selected file(s)?')) {
                var form = document.createElement('form');
                form.method = 'post';
                form.action = 'bulk_delete.php';
                ids.forEach(function (id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    document.querySelectorAll('.hash-label').forEach(function (label) {
        var item = label.closest('.checksum-item');
        var code = item ? item.querySelector('.hash-value') : null;
        label.addEventListener('click', function () {
            var hash = label.dataset.hash;
            if (!hash || !code) return;
            if (code.textContent === hash) {
                code.textContent = '';
                code.classList.remove('visible');
            } else {
                code.textContent = hash;
                code.classList.add('visible');
            }
        });
    });
    document.addEventListener('click', function (e) {
        if (e.target.closest('.hash-label') || e.target.closest('.hash-value') || e.target.closest('.btn-copy')) return;
        document.querySelectorAll('.hash-value.visible').forEach(function (el) {
            el.textContent = '';
            el.classList.remove('visible');
        });
    });
    document.querySelectorAll('.btn-copy').forEach(function (btn) {
        var origHtml = btn.innerHTML;
        btn.addEventListener('click', function () {
            var val = btn.dataset.copy;
            if (val && navigator.clipboard) navigator.clipboard.writeText(val);
            btn.innerHTML = '<svg class="icon" aria-hidden="true" width="24" height="24"><use href="assets/icons.svg#icon-check"/></svg>';
            setTimeout(function () { btn.innerHTML = origHtml; }, 1500);
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
