<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();
require_once __DIR__ . '/config/db.php';
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

    <?php if (isset($_GET['deleted'])): ?>
        <div class="success">✅ <code><?= sanitize_data($_GET['deleted']) ?></code> deleted successfully.</div>
    <?php elseif (isset($_GET['uploaded'])): ?>
        <div class="success">✅ File uploaded successfully.</div>
    <?php endif; ?>

    <?php if ($files): ?>
        <div class="file-list">
            <div class="file-row-dashboard file-header">
                <div>Filename</div>
                <div>Type</div>
                <div>Size</div>
                <div>Uploaded</div>
                <div>Expires</div>
                <div>Thumbnail</div>
                <div>Actions</div>
            </div>

            <?php foreach ($files as $file): ?>
                <div class="file-row-dashboard">
                    <div><?= sanitize_data($file['original_name']) ?></div>
                    <div title="<?= sanitize_data($file['filetype']) ?>">
                        <?= sanitize_data(get_friendly_filetype($file['filetype'])) ?>
                    </div>
                    <div><?= number_format($file['filesize'] / 1024, 2) ?> KB</div>
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
                        <a href="download.php?id=<?= $file['id'] ?>" class="btn btn-small">Download</a>
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
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
