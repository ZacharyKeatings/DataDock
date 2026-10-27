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
    echo '<div class="page-section"><h2 class="page-title">Share not found</h2><div class="empty-state"><p>This link is missing or not valid.</p></div></div>';
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
    echo '<div class="page-section"><h2 class="page-title">Share not found</h2><div class="empty-state"><p>This share link was not found.</p></div></div>';
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
        echo '<div class="page-section"><h2 class="page-title">Share expired</h2><div class="empty-state"><p>This share link has expired.</p></div></div>';
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
        <div class="empty-state"><p>No files in this share.</p></div>
    <?php else: ?>
        <div class="file-list file-list-share">
            <div class="file-row file-header">
                <div>File</div>
                <div>Size</div>
                <div>Download</div>
            </div>
            <?php foreach ($rows as $r):
                $fid = (int) $r['id'];
                $dl = 'download.php?sf=' . rawurlencode($token) . '&id=' . $fid;
                $hasThumb = !empty($r['thumbnail_path']) && str_starts_with((string) $r['filetype'], 'image/');
                ?>
                <div class="share-file">
                    <div class="file-row">
                        <div class="file-name-cell">
                            <?php if ($hasThumb): ?>
                                <img src="thumbnail.php?id=<?= $fid ?>&amp;sf=<?= sanitize_data($token) ?>" alt="" class="thumbnail-inline">
                            <?php else: ?>
                                <?= render_file_icon(get_file_icon($r['filetype'], $r['original_name'] ?? '')) ?>
                            <?php endif; ?>
                            <span><?= sanitize_data($r['original_name'] ?? $r['filename']) ?></span>
                        </div>
                        <div><?= format_filesize((int) ($r['filesize'] ?? 0)) ?></div>
                        <div><a href="<?= sanitize_data($dl) ?>" class="btn btn-primary btn-small">Download</a></div>
                    </div>
                    <?php if (!empty($r['recipient_note'])): ?>
                        <p class="share-file-note"><strong>Note:</strong> <?= nl2br(sanitize_data($r['recipient_note'])) ?></p>
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
