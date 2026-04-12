<?php
/**
 * Public page: temporary share folder (one link, many files, optional note per file).
 */
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();

$token = isset($_GET['t']) ? trim((string) $_GET['t']) : '';
if (strlen($token) !== 64 || !ctype_xdigit($token)) {
    http_response_code(404);
    $pageTitle = 'Share not found';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="page-section"><p>Invalid or missing share link.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT id, user_id, title, expires_at, created_at FROM share_folders WHERE token = ?');
$stmt->execute([$token]);
$bundle = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$bundle) {
    http_response_code(404);
    $pageTitle = 'Share not found';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="page-section"><p>This share link was not found.</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$exp = $bundle['expires_at'] ?? null;
if ($exp !== null && $exp !== '') {
    $ts = strtotime((string) $exp . ' UTC');
    if ($ts !== false && $ts < time()) {
        http_response_code(410);
        $pageTitle = 'Share expired';
        require_once __DIR__ . '/includes/header.php';
        echo '<div class="page-section"><p>This share link has expired.</p></div>';
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }
}

$sfId = (int) $bundle['id'];
$stmt = $pdo->prepare('
    SELECT f.id, f.original_name, f.filename, f.filetype, f.filesize, f.thumbnail_path,
           sff.recipient_note, sff.sort_order
    FROM share_folder_files sff
    INNER JOIN files f ON f.id = sff.file_id
    WHERE sff.share_folder_id = ?
    AND f.deleted_at IS NULL
    AND (f.quarantine_status = \'approved\' OR f.quarantine_status IS NULL)
    ORDER BY sff.sort_order ASC, f.id ASC
');
$stmt->execute([$sfId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Shared files';
$siteName = get_site_name();
require_once __DIR__ . '/includes/header.php';
?>
<div class="page-section">
    <h2 class="page-title"><?= sanitize_data($bundle['title'] !== null && $bundle['title'] !== '' ? $bundle['title'] : 'Shared files') ?></h2>
    <?php if ($exp !== null && $exp !== ''): ?>
        <p class="settings-hint">Link expires: <span class="utc-datetime" data-utc="<?= sanitize_data($exp) ?>"></span></p>
    <?php else: ?>
        <p class="settings-hint">This link does not expire automatically.</p>
    <?php endif; ?>

    <?php if (empty($rows)): ?>
        <p>No files in this share.</p>
    <?php else: ?>
        <div class="file-list" style="margin-top:1rem;">
            <div class="file-row file-header">
                <div class="file-preview-cell">Preview</div>
                <div>File</div>
                <div>Size</div>
                <div>Download</div>
            </div>
            <?php foreach ($rows as $r):
                $fid = (int) $r['id'];
                $dl = 'download.php?sf=' . rawurlencode($token) . '&id=' . $fid;
                ?>
                <div class="file-row-expandable">
                    <div class="file-row file-row-primary">
                        <div class="file-preview-cell">
                            <?php if (!empty($r['thumbnail_path']) && str_starts_with((string) $r['filetype'], 'image/')): ?>
                                <img src="thumbnail.php?id=<?= $fid ?>&amp;sf=<?= sanitize_data($token) ?>" alt="" class="thumbnail-small">
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                        <div><?= render_file_icon(get_file_icon($r['filetype'], $r['original_name'] ?? '')) ?> <?= sanitize_data($r['original_name'] ?? $r['filename']) ?></div>
                        <div><?= format_filesize((int) ($r['filesize'] ?? 0)) ?></div>
                        <div><a href="<?= sanitize_data($dl) ?>" class="btn btn-small">Download</a></div>
                    </div>
                    <?php if (!empty($r['recipient_note'])): ?>
                        <div class="file-row-details" style="padding:0.5rem 1rem;background:var(--panel-bg, #f5f5f5);border-radius:4px;margin-bottom:0.5rem;">
                            <strong>Note:</strong> <?= nl2br(sanitize_data($r['recipient_note'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.utc-datetime').forEach(el => {
        const utc = el.dataset.utc;
        if (utc) {
            const local = new Date(utc + ' UTC');
            el.textContent = local.toLocaleString();
        }
    });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php';
