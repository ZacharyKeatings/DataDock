<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = "Home";
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
require_once 'includes/header.php';

$publicBrowsingEnabled = !empty($settings['public_browsing_enabled']);
$pastesEnabled = !isset($settings['pastes_enabled']) || !empty($settings['pastes_enabled']);
$readOnly = datadock_read_only_enabled($settings);
$guestUploadsEnabled = !empty($settings['guest_uploads']['enabled']);
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
    <div class="dash-heading">
        <h2 class="page-title">Welcome to <?= sanitize_data($siteName) ?></h2>
        <div class="home-actions">
            <?php if ($userId): ?>
                <a href="dashboard.php" class="btn btn-quiet">Your files</a>
                <?php if (!$readOnly): ?>
                <a href="upload.php" class="btn btn-primary">Upload</a>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($guestUploadsEnabled && !$readOnly): ?>
                <a href="upload.php" class="btn btn-primary">Upload</a>
                <?php endif; ?>
                <a href="login.php" class="btn btn-quiet">Login</a>
                <?php if (!$readOnly): ?>
                <a href="register.php" class="btn btn-primary">Register</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $welcomeMessage = trim($settings['welcome_message'] ?? '');
    if (!empty($welcomeMessage)):
    ?>
    <div class="welcome-banner"><?= nl2br(sanitize_data($welcomeMessage)) ?></div>
    <?php endif; ?>
    <p class="page-description"><?= $publicBrowsingEnabled ? 'Public files are listed below.' : 'Recent uploads are listed below. Download links stay off until public browsing is enabled.' ?></p>
    <?php if ($pastesEnabled && !$readOnly && ($userId || $guestUploadsEnabled)): ?>
    <p class="page-description"><a href="paste.php">Share plaintext</a> with an unlisted link.</p>
    <?php endif; ?>

    <?php if ($recentFiles): ?>
        <div class="file-list file-list-index file-list-expandable<?= $publicBrowsingEnabled ? ' file-list-has-download' : '' ?>">
            <div class="file-row file-header">
                <div class="file-row-toggle-cell" aria-hidden="true"></div>
                <div>Filename</div>
                <div>Size</div>
                <?php if ($publicBrowsingEnabled): ?><div>Download</div><?php endif; ?>
            </div>
            <?php foreach ($recentFiles as $file):
                $rid = (int) $file['id'];
                $hasThumb = !empty($file['thumbnail_path']) && str_starts_with((string) ($file['filetype'] ?? ''), 'image/');
            ?>
                <div class="file-row-expandable">
                    <div class="file-row file-row-primary">
                        <div class="file-row-toggle-cell">
                            <button type="button" class="file-row-toggle" id="index-toggle-<?= $rid ?>" aria-expanded="false" aria-controls="index-details-<?= $rid ?>" aria-label="Show details for <?= htmlspecialchars($file['original_name'] ?? 'file', ENT_QUOTES, 'UTF-8') ?>">
                                <span class="file-row-toggle-icon" aria-hidden="true">▸</span>
                            </button>
                        </div>
                        <div class="file-name-cell">
                            <?php if ($hasThumb): ?>
                                <img src="thumbnail.php?id=<?= $rid ?>" alt="" class="thumbnail-inline">
                            <?php else: ?>
                                <?= render_file_icon(get_file_icon($file['filetype'], $file['original_name'] ?? '')) ?>
                            <?php endif; ?>
                            <span><?= sanitize_data($file['original_name']) ?></span>
                        </div>
                        <div><?= format_filesize($file['filesize']) ?></div>
                        <?php if ($publicBrowsingEnabled): ?>
                        <div class="file-actions">
                            <a href="download.php?id=<?= $rid ?>" class="btn btn-primary btn-small">Download</a>
                            <?php if (!empty($userId) && (int) ($file['user_id'] ?? 0) !== (int) $userId): ?>
                                <a href="report_file.php?id=<?= $rid ?>&amp;return_to=index.php" class="btn btn-quiet btn-small">Report</a>
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
        <div class="empty-state">
            <p>No files yet.</p>
            <?php if ($userId && !$readOnly): ?>
            <a href="upload.php" class="btn btn-primary">Upload files</a>
            <?php elseif (!$userId && $guestUploadsEnabled && !$readOnly): ?>
            <a href="upload.php" class="btn btn-primary">Upload files</a>
            <?php elseif (!$userId && !$readOnly): ?>
            <a href="register.php" class="btn btn-primary">Create an account</a>
            <?php endif; ?>
        </div>
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
