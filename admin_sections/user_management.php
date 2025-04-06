<h2 class="page-title">User Management</h2>

<?php if ($users): ?>
    <div class="file-list">
        <div class="file-row-user-management file-header">
            <div>Registered</div>
            <div>Username</div>
            <div>Email</div>
            <div>Files</div>
            <div>Storage</div>
            <div>Role</div>
            <div>Delete</div>
        </div>

        <?php foreach ($users as $user): ?>
            <div class="file-row-user-management">
                <div><?= sanitize_data($user['created_at']) ?></div>
                <div><?= sanitize_data($user['username']) ?></div>
                <div><?= sanitize_data($user['email']) ?></div>
                <div><?= sanitize_data($user['file_count']) ?></div>
                <div><?= format_filesize($user['total_size']) ?></div>
                <div>
                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                        <form method="post" action="admin_sections/admin_change_role.php" onsubmit="return confirm('Change this user’s role?')" style="display: flex; gap: 0.5rem;">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <select name="role">
                                <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </form>
                    <?php else: ?>
                        <?= sanitize_data($user['role']) ?> (You)
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                        <form method="post" action="admin_sections/admin_delete_user.php" onsubmit="return confirm('Delete this user?');">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button type="submit" class="btn btn-primary">Delete</button>
                        </form>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>No users found.</p>
<?php endif; ?>
