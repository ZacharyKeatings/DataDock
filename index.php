<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = "Home";
require_once 'includes/header.php';

// Get latest 5 uploaded files (includes guest uploads via LEFT JOIN)
$stmt = $pdo->prepare("SELECT files.*, users.username FROM files 
    LEFT JOIN users ON files.user_id = users.id 
    WHERE (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())
    ORDER BY upload_date DESC 
    LIMIT 5");
$stmt->execute();
$recentFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-section">
    <h2 class="page-title">Welcome to <?= sanitize_data($siteName) ?></h2>
    <p class="page-description">This site allows registered users to upload files and manage them securely. Below are the most recent uploads:</p>

    <?php if ($recentFiles): ?>
        <div class="file-list">
            <div class="file-row file-header">
                <div>Filename</div>
                <div>User</div>
                <div>Type</div>
                <div>Size</div>
                <div>Uploaded</div>
                <div>Preview</div>
            </div>
            <?php foreach ($recentFiles as $file): ?>
                <div class="file-row">
                    <div><?= sanitize_data($file['original_name']) ?></div>
                    <div><?= sanitize_data($file['username'] ?? 'Guest') ?></div>
                    <div title="<?= sanitize_data($file['filetype']) ?>">
                        <?= sanitize_data(get_friendly_filetype($file['filetype'])) ?>
                    </div>
                    <div><?= format_filesize($file['filesize']) ?></div>
                    <div><span class="utc-datetime" data-utc="<?= sanitize_data($file['upload_date']) ?>"></span></div>
                    <div>
                        <?php if ($file['thumbnail_path'] && str_starts_with($file['filetype'], 'image/')): ?>
                            <img src="thumbnails/<?= sanitize_data($file['thumbnail_path']) ?>" alt="Thumbnail" class="thumbnail-small">
                        <?php else: ?>
                            —
                        <?php endif; ?>
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
            el.textContent = local.toLocaleString();
        } else {
            el.textContent = '—';
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
