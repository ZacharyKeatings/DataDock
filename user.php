<?php
/**
 * Public user profile page. No login required.
 * Shows display name (or username), member since, public stats, and list of public files.
 */
require_once __DIR__ . '/includes/auth.php';
init_session();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();

$usernameParam = trim($_GET['username'] ?? '');
if ($usernameParam === '') {
    $_SESSION['flash_error'][] = 'No user specified.';
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, display_name, avatar, bio, created_at FROM users WHERE username = ?");
$stmt->execute([$usernameParam]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    $_SESSION['flash_error'][] = 'User not found.';
    header('Location: index.php');
    exit;
}

$displayName = trim($user['display_name'] ?? '') !== '' ? $user['display_name'] : $user['username'];
$publicBrowsingEnabled = !empty($settings['public_browsing_enabled']);

// Public stats: count, total size, total downloads for current public files
$statsStmt = $pdo->prepare("
    SELECT COUNT(*) AS file_count,
           COALESCE(SUM(filesize), 0) AS total_size,
           COALESCE(SUM(download_count), 0) AS total_downloads
    FROM files
    WHERE user_id = ? AND is_public = 1 AND deleted_at IS NULL AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())
");
$statsStmt->execute([$user['id']]);
$publicStats = $statsStmt->fetch(PDO::FETCH_ASSOC);
$publicFileCount = (int) ($publicStats['file_count'] ?? 0);
$publicTotalSize = (int) ($publicStats['total_size'] ?? 0);
$publicTotalDownloads = (int) ($publicStats['total_downloads'] ?? 0);

// Most common file type among public files
$topTypeStmt = $pdo->prepare("
    SELECT filetype, COUNT(*) AS cnt
    FROM files
    WHERE user_id = ? AND is_public = 1 AND deleted_at IS NULL AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())
      AND filetype IS NOT NULL AND filetype != ''
    GROUP BY filetype
    ORDER BY cnt DESC
    LIMIT 1
");
$topTypeStmt->execute([$user['id']]);
$topTypeRow = $topTypeStmt->fetch(PDO::FETCH_ASSOC);
$topPublicFileType = $topTypeRow ? get_friendly_filetype($topTypeRow['filetype']) : null;

// List of public files (for table)
$filesStmt = $pdo->prepare("
    SELECT id, original_name, filetype, filesize, download_count, upload_date, thumbnail_path
    FROM files
    WHERE user_id = ? AND is_public = 1 AND deleted_at IS NULL AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())
    ORDER BY upload_date DESC
");
$filesStmt->execute([$user['id']]);
$publicFiles = $filesStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = $displayName;
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section">
    <div class="profile-public-head">
        <?php if (!empty(trim($user['avatar'] ?? ''))): ?>
        <div class="profile-public-avatar">
            <?php if (preg_match('#^https?://#i', $user['avatar'])): ?>
            <img src="<?= sanitize_data($user['avatar']) ?>" alt="" class="profile-avatar-img" width="96" height="96" loading="lazy">
            <?php else: ?>
            <img src="avatar.php?username=<?= urlencode($user['username']) ?>" alt="" class="profile-avatar-img" width="96" height="96" loading="lazy">
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="profile-public-title-wrap">
            <h2 class="page-title"><?= sanitize_data($displayName) ?></h2>
            <?php if ($displayName !== $user['username']): ?>
            <p class="profile-public-username">@<?= sanitize_data($user['username']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="settings-card profile-stats-card" style="margin-bottom: 1.5rem;">
        <h3 class="settings-card-title">Profile</h3>
        <div class="settings-card-body">
            <p><strong>Member since</strong> <?= format_datetime_display($user['created_at']) ?></p>
        </div>
    </div>

    <?php if (!empty(trim($user['bio'] ?? ''))): ?>
    <div class="settings-card profile-stats-card" style="margin-bottom: 1.5rem;">
        <h3 class="settings-card-title">Bio</h3>
        <div class="settings-card-body profile-public-bio">
            <?= nl2br(sanitize_data($user['bio'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="settings-card profile-stats-card" style="margin-bottom: 1.5rem;">
        <h3 class="settings-card-title">Public file stats</h3>
        <div class="settings-card-body profile-stats-grid">
            <div class="profile-stat">
                <span class="profile-stat-value"><?= $publicFileCount ?></span>
                <span class="profile-stat-label">Public files</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><?= format_filesize($publicTotalSize) ?></span>
                <span class="profile-stat-label">Total size (public)</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><?= number_format($publicTotalDownloads) ?></span>
                <span class="profile-stat-label">Total downloads</span>
            </div>
            <?php if ($topPublicFileType): ?>
            <div class="profile-stat">
                <span class="profile-stat-value"><?= sanitize_data($topPublicFileType) ?></span>
                <span class="profile-stat-label">Most common type</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($publicFileCount > 0): ?>
    <div class="page-section">
        <h3 class="page-title">Public files</h3>
        <div class="file-list file-list-index file-list-expandable<?= $publicBrowsingEnabled ? ' file-list-has-download' : '' ?>">
            <div class="file-row file-header">
                <div class="file-row-toggle-cell" aria-hidden="true"></div>
                <div class="file-preview-cell">Preview</div>
                <div>Filename</div>
                <div>Size</div>
                <?php if ($publicBrowsingEnabled): ?><div>Download</div><?php endif; ?>
            </div>
            <?php foreach ($publicFiles as $file):
                $pfid = (int) $file['id'];
            ?>
                <div class="file-row-expandable">
                    <div class="file-row file-row-primary">
                        <div class="file-row-toggle-cell">
                            <button type="button" class="file-row-toggle" id="user-toggle-<?= $pfid ?>" aria-expanded="false" aria-controls="user-details-<?= $pfid ?>" aria-label="Show details for <?= htmlspecialchars($file['original_name'] ?? 'file', ENT_QUOTES, 'UTF-8') ?>">
                                <span class="file-row-toggle-icon" aria-hidden="true">▸</span>
                            </button>
                        </div>
                        <div class="file-preview-cell">
                            <?php if (!empty($file['thumbnail_path']) && str_starts_with($file['filetype'] ?? '', 'image/')): ?>
                                <img src="thumbnail.php?id=<?= $pfid ?>" alt="Thumbnail" class="thumbnail-small">
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                        <div><?= render_file_icon(get_file_icon($file['filetype'], $file['original_name'] ?? '')) ?> <?= sanitize_data($file['original_name'] ?? $file['id']) ?></div>
                        <div><?= format_filesize((int) ($file['filesize'] ?? 0)) ?></div>
                        <?php if ($publicBrowsingEnabled): ?>
                        <div><a href="download.php?id=<?= $pfid ?>" class="btn btn-small">Download</a></div>
                        <?php endif; ?>
                    </div>
                    <div class="file-row-details" id="user-details-<?= $pfid ?>" role="region" aria-labelledby="user-toggle-<?= $pfid ?>" hidden>
                        <div class="file-row-details-inner">
                            <dl class="file-details-grid">
                                <dt>Type</dt>
                                <dd title="<?= sanitize_data($file['filetype'] ?? '') ?>"><?= sanitize_data(get_friendly_filetype($file['filetype'] ?? '')) ?></dd>
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
    </div>
    <?php else: ?>
    <p class="profile-no-files">No public files yet.</p>
    <?php endif; ?>

    <?php if (!empty($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id']): ?>
    <p style="margin-top: 1.5rem;"><a href="profile.php" class="btn btn-primary">Edit your profile</a></p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.utc-datetime').forEach(el => {
        var utc = el.dataset.utc;
        if (utc) {
            var local = new Date(utc + ' UTC');
            el.textContent = local.toLocaleDateString() + ' ' + local.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
