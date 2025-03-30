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