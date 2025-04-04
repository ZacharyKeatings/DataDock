<h2 class="page-title">File Management</h2>

<form method="post" class="form" style="margin-bottom: 2rem;">
    <input type="hidden" name="purge" value="1">
    <button type="submit" class="btn btn-primary">Purge Expired Files</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purge'])) {
    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("SELECT * FROM files WHERE expiry_date IS NOT NULL AND expiry_date < ?");
    $stmt->execute([$now]);
    $expiredFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $deletedCount = 0;
    $freedBytes = 0;
    $filetypeBreakdown = [];
    $errors = [];

    foreach ($expiredFiles as $file) {
        $filePath = __DIR__ . '/uploads/' . $file['filename'];
        $thumbPath = __DIR__ . '/thumbnails/' . $file['thumbnail_path'];

        if (file_exists($filePath)) {
            if (!unlink($filePath)) {
                $errors[] = "Failed to delete file: " . $file['filename'];
                continue;
            } else {
                $freedBytes += $file['filesize'];
                $type = $file['filetype'] ?? 'unknown';
                $filetypeBreakdown[$type] = ($filetypeBreakdown[$type] ?? 0) + 1;
            }
        }

        if (!empty($file['thumbnail_path']) && file_exists($thumbPath)) {
            unlink($thumbPath);
        }

        $stmtDel = $pdo->prepare("DELETE FROM files WHERE id = ?");
        $stmtDel->execute([$file['id']]);
        $deletedCount++;
    }

    echo "<div class='success'>✅ Purged $deletedCount file" . ($deletedCount !== 1 ? 's' : '') . ".</div>";
    echo "<p><strong>Total space freed:</strong> " . format_filesize($freedBytes) . "</p>";

    if (!empty($filetypeBreakdown)) {
        echo "<p><strong>Filetype breakdown:</strong></p><ul>";
        foreach ($filetypeBreakdown as $type => $count) {
            echo "<li>" . sanitize_data($type) . ": $count</li>";
        }
        echo "</ul>";
    }

    if (!empty($errors)) {
        echo "<div class='error'><strong>Some files could not be deleted:</strong><ul>";
        foreach ($errors as $e) {
            echo "<li>" . sanitize_data($e) . "</li>";
        }
        echo "</ul></div>";
    }
}

$stmt = $pdo->query("
    SELECT f.*, u.username 
    FROM files f
    JOIN users u ON f.user_id = u.id
    ORDER BY f.upload_date DESC
");
$allFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h3 class="page-subtitle">All Uploaded Files</h3>

<?php if ($allFiles): ?>
    <div class="file-list">
        <div class="file-row-file-management file-header">
            <div>User</div>
            <div>Filename</div>
            <div>Type</div>
            <div>Size</div>
            <div>Uploaded</div>
            <div>Expires</div>
            <div>Preview</div>
            <div>Actions</div>
        </div>

        <?php foreach ($allFiles as $file): ?>
            <div class="file-row-file-management">
                <div><?= sanitize_data($file['username']) ?></div>
                <div><?= sanitize_data($file['original_name']) ?></div>
                <div title="<?= sanitize_data($file['filetype']) ?>">
                    <?= sanitize_data(get_friendly_filetype($file['filetype'])) ?>
                </div>
                <div><?= format_filesize($file['filesize']) ?></div>
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
                    <a href="download.php?id=<?= $file['id'] ?>" class="btn btn-small">Download</a> |
                    <a href="delete.php?id=<?= $file['id'] ?>" class="btn btn-small btn-danger" onclick="return confirm('Delete this file?')">Delete</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>No uploaded files found.</p>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
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
