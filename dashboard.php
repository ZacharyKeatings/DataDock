<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = "Your Files";
require_once __DIR__ . '/includes/header.php';

require_login();

$userId = $_SESSION['user_id'];

// Fetch user's files
$stmt = $pdo->prepare("SELECT * FROM files WHERE user_id = ? ORDER BY upload_date DESC");
$stmt->execute([$userId]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Your Uploaded Files</h2>

<?php if (isset($_GET['deleted'])): ?>
    <div class="success">✅ <code><?= sanitize_data($_GET['deleted']) ?></code> deleted successfully.</div>
<?php elseif (isset($_GET['uploaded'])): ?>
    <div class="success">✅ File uploaded successfully.</div>
<?php endif; ?>

<?php if ($files): ?>
    <table>
        <thead>
            <tr>
                <th>Filename</th>
                <th>Type</th>
                <th>Size</th>
                <th>Uploaded</th>
                <th>Expires</th>
                <th>Thumbnail</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($files as $file): ?>
            <tr>
                <td><?= sanitize_data($file['original_name']) ?></td>
                <td><?= sanitize_data($file['filetype']) ?></td>
                <td><?= number_format($file['filesize'] / 1024, 2) ?> KB</td>
                <td><?= sanitize_data($file['upload_date']) ?></td>
                <td><?= $file['expiry_date'] ? sanitize_data($file['expiry_date']) : 'Never' ?></td>
                <td>
                    <?php if ($file['thumbnail_path'] && str_starts_with($file['filetype'], 'image/')): ?>
                        <img src="thumbnails/<?= sanitize_data($file['thumbnail_path']) ?>" alt="Thumb" style="height: 40px;">
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td>
                    <a href="download.php?id=<?= $file['id'] ?>">Download</a> |
                    <a href="delete.php?id=<?= $file['id'] ?>" onclick="return confirm('Delete this file?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>You haven't uploaded any files yet.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
