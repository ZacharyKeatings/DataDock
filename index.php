<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = "Home";
require_once 'config/settings.php';
require_once 'includes/header.php';

$publicBrowsingEnabled = !empty($settings['public_browsing_enabled']);
$userId = $_SESSION['user_id'] ?? null;

// Get files to display
if ($publicBrowsingEnabled) {
    // Show only public, approved files (quarantined/pending never visible publicly)
    $stmt = $pdo->prepare("SELECT files.*, users.username FROM files 
        LEFT JOIN users ON files.user_id = users.id 
        WHERE files.is_public = 1 
        AND files.deleted_at IS NULL
        AND (files.quarantine_status = 'approved' OR files.quarantine_status IS NULL)
        AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())
        ORDER BY upload_date DESC 
        LIMIT 20");
    $stmt->execute();
} else {
    // Default: latest 5 uploads (no download links for anons); only approved
    $stmt = $pdo->prepare("SELECT files.*, users.username FROM files 
        LEFT JOIN users ON files.user_id = users.id 
        WHERE files.deleted_at IS NULL
        AND (files.quarantine_status = 'approved' OR files.quarantine_status IS NULL)
        AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())
        ORDER BY upload_date DESC 
        LIMIT 5");
    $stmt->execute();
}
$recentFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-section">
    <h2 class="page-title">Welcome to <?= sanitize_data($siteName) ?></h2>
    <?php
    $welcomeMessage = trim($settings['welcome_message'] ?? '');
    if (!empty($welcomeMessage)):
    ?>
    <div class="welcome-banner"><?= nl2br(sanitize_data($welcomeMessage)) ?></div>
    <?php endif; ?>
    <p class="page-description">This site allows registered users to upload files and manage them securely.<?= $publicBrowsingEnabled ? ' Below are publicly shared files:' : ' Below are the most recent uploads:' ?></p>

    <?php if ($recentFiles): ?>
        <div class="file-list file-list-index file-list-expandable<?= $publicBrowsingEnabled ? ' file-list-has-download' : '' ?>">
            <div class="file-row file-header">
                <div class="file-row-toggle-cell" aria-hidden="true"></div>
                <div class="file-preview-cell">Preview</div>
                <div>Filename</div>
                <div>Size</div>
                <?php if ($publicBrowsingEnabled): ?><div>Download</div><?php endif; ?>
            </div>
            <?php foreach ($recentFiles as $file):
                $rid = (int) $file['id'];
            ?>
                <div class="file-row-expandable">
                    <div class="file-row file-row-primary">
                        <div class="file-row-toggle-cell">
                            <button type="button" class="file-row-toggle" id="index-toggle-<?= $rid ?>" aria-expanded="false" aria-controls="index-details-<?= $rid ?>" aria-label="Show details for <?= htmlspecialchars($file['original_name'] ?? 'file', ENT_QUOTES, 'UTF-8') ?>">
                                <span class="file-row-toggle-icon" aria-hidden="true">▸</span>
                            </button>
                        </div>
                        <div class="file-preview-cell">
                            <?php if ($file['thumbnail_path'] && str_starts_with($file['filetype'], 'image/')): ?>
                                <img src="thumbnail.php?id=<?= $rid ?>" alt="Thumbnail" class="thumbnail-small">
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                        <div><?= render_file_icon(get_file_icon($file['filetype'], $file['original_name'] ?? '')) ?> <?= sanitize_data($file['original_name']) ?></div>
                        <div><?= format_filesize($file['filesize']) ?></div>
                        <?php if ($publicBrowsingEnabled): ?>
                        <div style="display:flex;gap:0.35rem;flex-wrap:wrap;">
                            <a href="download.php?id=<?= $rid ?>" class="btn btn-small">Download</a>
                            <?php if (!empty($userId) && (int) ($file['user_id'] ?? 0) !== (int) $userId): ?>
                                <a href="report_file.php?id=<?= $rid ?>&amp;return_to=index.php" class="btn btn-small">Report</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="file-row-details" id="index-details-<?= $rid ?>" role="region" aria-labelledby="index-toggle-<?= $rid ?>" hidden>
                        <div class="file-row-details-inner">
                            <dl class="file-details-grid">
                                <dt>User</dt>
                                <dd><?= user_profile_link($file['username'] ?? null) ?></dd>
                                <dt>Type</dt>
                                <dd title="<?= sanitize_data($file['filetype']) ?>"><?= sanitize_data(get_friendly_filetype($file['filetype'])) ?></dd>
                                <dt>Downloads</dt>
                                <dd><?= (int) ($file['download_count'] ?? 0) ?></dd>
                                <dt>Uploaded</dt>
                                <dd><span class="utc-datetime" data-utc="<?= sanitize_data($file['upload_date']) ?>"></span></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>No files uploaded yet.</p>
    <?php endif; ?>
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

<?php require_once 'includes/footer.php'; ?>
