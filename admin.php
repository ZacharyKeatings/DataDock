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

// Handle tab selection
$section = $_GET['section'] ?? 'site';

// Handle Site Settings form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section === 'site') {
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
?>

<h2>Admin Panel</h2>
<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="success"><?= sanitize_data($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<div style="display: flex; gap: 20px;">
    <aside style="min-width: 200px;">
        <nav class="sidebar">
            <ul style="list-style: none; padding: 0;">
                <li><a href="?section=site"<?= $section === 'site' ? ' class="active"' : '' ?>>Site Settings</a></li>
                <li><a href="?section=users"<?= $section === 'users' ? ' class="active"' : '' ?>>User Management</a></li>
                <li><a href="?section=files"<?= $section === 'files' ? ' class="active"' : '' ?>>File Management</a></li>
            </ul>
        </nav>
    </aside>

    <main style="flex-grow: 1;">
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

        <?php if ($section === 'site'): ?>
            <h3>Site Settings</h3>
            <form method="post">
                <label for="site_name">Site Name</label>
                <input type="text" name="site_name" id="site_name" value="<?= sanitize_data($siteName) ?>" required>
                <button type="submit">Update</button>
            </form>
        <?php elseif ($section === 'users'): ?>
            <h2>User Management</h2>

            <?php
            // Handle delete request
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
                $deleteUserId = (int) $_POST['delete_user_id'];

                // Prevent deleting your own account
                if ($deleteUserId === $_SESSION['user_id']) {
                    echo "<div class='error'>❌ You cannot delete your own admin account.</div>";
                } else {
                    // Delete user's files (files table has ON DELETE CASCADE)
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$deleteUserId]);
                    echo "<div class='success'>✅ User ID $deleteUserId deleted successfully.</div>";
                }
            }

            // Fetch users with file stats
            $stmt = $pdo->query("
                SELECT u.id, u.username, u.email, u.role, u.created_at, 
                    COUNT(f.id) AS file_count, 
                    COALESCE(SUM(f.filesize), 0) AS total_size
                FROM users u
                LEFT JOIN files f ON u.id = f.user_id
                GROUP BY u.id
                ORDER BY u.created_at DESC
            ");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <table>
                <thead>
                    <tr>
                        <th>Registered</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Files</th>
                        <th>Storage Used</th>
                        <th>Actions</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= sanitize_data($user['created_at']) ?></td>
                        <td><?= sanitize_data($user['username']) ?></td>
                        <td><?= sanitize_data($user['email']) ?></td>
                        <td><?= sanitize_data($user['file_count']) ?></td>
                        <td><?= format_filesize($user['total_size']) ?></td>
                        <td>
                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <form method="post" action="admin_change_role.php" onsubmit="return confirm('Are you sure you want to change this user’s role?')" style="display:inline-block;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <select name="role">
                                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                    <button type="submit">Update</button>
                                </form>
                            <?php else: ?>
                                <?= sanitize_data($user['role']) ?> (You)
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <form method="post" action="admin_delete_user.php" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit">Delete</button>
                                </form>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
        <?php elseif ($section === 'files'): ?>
            <h2>File Management</h2>
            <form method="post">
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
            ?>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>