<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_admin();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Admin Panel";
require_once __DIR__ . '/includes/header.php';

$settingsFile = __DIR__ . '/config/settings.php';
$siteName = get_site_name();
$success = '';
$errors = [];
$purgeResults = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update site name
    if (isset($_POST['site_name'])) {
        $newName = trim($_POST['site_name'] ?? '');
        if ($newName === '') {
            $errors[] = "Site name cannot be empty.";
        } else {
            $updatedSettings = "<?php\n\$settings = [\n    'site_name' => " . var_export($newName, true) . "\n];\n?>";
            if (file_put_contents($settingsFile, $updatedSettings)) {
                $success = "Site name updated successfully.";
                $siteName = $newName;
            } else {
                $errors[] = "Failed to update site name.";
            }
        }
    }

    // Purge expired files
    if (isset($_POST['purge_files'])) {
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("SELECT * FROM files WHERE expiry_date IS NOT NULL AND expiry_date < ?");
        $stmt->execute([$now]);
        $expiredFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $deletedCount = 0;
        $totalFreed = 0;
        $fileTypeCounts = [];
        $purgeErrors = [];

        foreach ($expiredFiles as $file) {
            $filePath = __DIR__ . '/uploads/' . $file['filename'];
            $thumbPath = __DIR__ . '/thumbnails/' . $file['thumbnail_path'];

            if (file_exists($filePath)) {
                if (unlink($filePath)) {
                    $deletedCount++;
                    $totalFreed += $file['filesize'];
                    $type = $file['filetype'];
                    $fileTypeCounts[$type] = ($fileTypeCounts[$type] ?? 0) + 1;

                    if (!empty($file['thumbnail_path']) && file_exists($thumbPath)) {
                        unlink($thumbPath);
                    }

                    $stmtDel = $pdo->prepare("DELETE FROM files WHERE id = ?");
                    $stmtDel->execute([$file['id']]);
                } else {
                    $purgeErrors[] = "Failed to delete file: " . sanitize_data($file['filename']);
                }
            }
        }

        if ($deletedCount > 0) {
            $purgeResults .= "<div class='success'>✅ Purged {$deletedCount} expired file(s).<br>";
            $purgeResults .= "💾 Total freed: " . format_filesize($totalFreed) . "<br>";
            $purgeResults .= "📂 Breakdown by type:<ul>";
            foreach ($fileTypeCounts as $type => $count) {
                $purgeResults .= "<li>" . sanitize_data($type) . ": {$count}</li>";
            }
            $purgeResults .= "</ul></div>";
        } else {
            $purgeResults .= "<div>No expired files found.</div>";
        }

        if (!empty($purgeErrors)) {
            $purgeResults .= "<div class='error'><strong>Some errors occurred:</strong><ul>";
            foreach ($purgeErrors as $e) {
                $purgeResults .= "<li>{$e}</li>";
            }
            $purgeResults .= "</ul></div>";
        }
    }
}
?>

<h2>Admin Panel</h2>

<?php if ($success): ?>
    <div class="success"><?= sanitize_data($success) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="error">
        <?php foreach ($errors as $error): ?>
            <div>• <?= sanitize_data($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post">
    <label for="site_name">Site Name</label>
    <input type="text" name="site_name" id="site_name" value="<?= sanitize_data($siteName) ?>" required>
    <button type="submit">Update</button>
</form>

<hr>

<h3>Purge Expired Files</h3>
<form method="post">
    <button type="submit" name="purge_files">Purge Now</button>
</form>

<?= $purgeResults ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>