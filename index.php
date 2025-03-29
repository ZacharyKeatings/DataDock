<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = "Home";
require_once 'includes/header.php';

// Get latest 5 uploaded files
$stmt = $pdo->prepare("SELECT files.*, users.username FROM files 
    JOIN users ON files.user_id = users.id 
    WHERE (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())
    ORDER BY upload_date DESC 
    LIMIT 5");
$stmt->execute();
$recentFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Welcome to <?= sanitize_data($siteName) ?></h2>
<p>This site allows registered users to upload files and manage them securely. Below are the most recent uploads:</p>

<?php if ($recentFiles): ?>
    <table>
        <thead>
            <tr>
                <th>Filename</th>
                <th>User</th>
                <th>Type</th>
                <th>Size</th>
                <th>Uploaded</th>
                <th>Preview</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentFiles as $file): ?>
                <tr>
                    <td><?= sanitize_data($file['original_name']) ?></td>
                    <td><?= sanitize_data($file['username']) ?></td>
                    <td><?= sanitize_data($file['filetype']) ?></td>
                    <td><?= format_filesize($file['filesize']) ?></td>
                    <td><span class="utc-datetime" data-utc="<?= sanitize_data($file['upload_date']) ?>"></span></td>
                    <td>
                        <?php if (str_starts_with($file['filetype'], 'image/')): ?>
                            <img src="thumbnails/<?= sanitize_data($file['thumbnail_path']) ?>" alt="Thumbnail" style="height: 40px;">
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No files uploaded yet.</p>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const elements = document.querySelectorAll('.utc-datetime');
    elements.forEach(el => {
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
