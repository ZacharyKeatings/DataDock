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
    // Show only public files when anonymous browsing is enabled
    $stmt = $pdo->prepare("SELECT files.*, users.username FROM files 
        LEFT JOIN users ON files.user_id = users.id 
        WHERE files.is_public = 1 
        AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())
        ORDER BY upload_date DESC 
        LIMIT 20");
    $stmt->execute();
} else {
    // Default: latest 5 uploads (no download links for anons)
    $stmt = $pdo->prepare("SELECT files.*, users.username FROM files 
        LEFT JOIN users ON files.user_id = users.id 
        WHERE (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())
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
        <div class="file-list file-list-index<?= $publicBrowsingEnabled ? ' file-list-has-download' : '' ?>">
            <div class="file-row file-header">
                <div>Filename</div>
                <div>User</div>
                <div>Type</div>
                <div>Size</div>
                <div>Downloads</div>
                <div>Uploaded</div>
                <div>Preview</div>
                <?php if ($publicBrowsingEnabled): ?><div>Download</div><?php endif; ?>
            </div>
            <?php foreach ($recentFiles as $file): ?>
                <div class="file-row">
                    <div><?= render_file_icon(get_file_icon($file['filetype'], $file['original_name'] ?? '')) ?> <?= sanitize_data($file['original_name']) ?></div>
                    <div><?= sanitize_data($file['username'] ?? 'Guest') ?></div>
                    <div title="<?= sanitize_data($file['filetype']) ?>">
                        <?= sanitize_data(get_friendly_filetype($file['filetype'])) ?>
                    </div>
                    <div><?= format_filesize($file['filesize']) ?></div>
                    <div><?= (int) ($file['download_count'] ?? 0) ?></div>
                    <div><span class="utc-datetime" data-utc="<?= sanitize_data($file['upload_date']) ?>"></span></div>
                    <div>
                        <?php if ($file['thumbnail_path'] && str_starts_with($file['filetype'], 'image/')): ?>
                            <img src="thumbnail.php?id=<?= (int)$file['id'] ?>" alt="Thumbnail" class="thumbnail-small">
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                    <?php if ($publicBrowsingEnabled): ?>
                    <div><a href="download.php?id=<?= (int)$file['id'] ?>" class="btn btn-small">Download</a></div>
                    <?php endif; ?>
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
