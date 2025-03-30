<h2>File Management</h2>

<form method="post" style="margin-bottom: 20px;">
    <input type="hidden" name="purge" value="1">
    <button type="submit">Purge Expired Files</button>
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

// List all uploaded files
$stmt = $pdo->query("
    SELECT f.*, u.username 
    FROM files f
    JOIN users u ON f.user_id = u.id
    ORDER BY f.upload_date DESC
");
$allFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h3>All Uploaded Files</h3>
<?php if ($allFiles): ?>
    <table>
        <thead>
            <tr>
                <th>User</th>
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
        <?php foreach ($allFiles as $file): ?>
            <tr>
                <td><?= sanitize_data($file['username']) ?></td>
                <td><?= sanitize_data($file['original_name']) ?></td>
                <td><?= sanitize_data($file['filetype']) ?></td>
                <td><?= format_filesize($file['filesize']) ?></td>
                <td><span class="utc-datetime" data-utc="<?= sanitize_data($file['upload_date']) ?>"></span></td>
                <td>
                    <?= $file['expiry_date']
                        ? '<span class="utc-datetime" data-utc="' . htmlspecialchars($file['expiry_date']) . '"></span>'
                        : 'Never' ?>
                </td>
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
    <p>No uploaded files found.</p>
<?php endif; ?>