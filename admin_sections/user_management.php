<h2 class="page-title">User Management</h2>

<?php
$inviteOnly = !empty($settings['invite_only_registration'] ?? false);
if ($inviteOnly): ?>
<div class="settings-card stack-gap">
    <h3 class="settings-card-title">Signup tokens (invite-only registration)</h3>
    <div class="settings-card-body">
        <p class="settings-hint">Generate a one-time signup link to share with new users. Token is valid for 7 days.</p>
        <form method="post" action="admin_sections/admin_generate_signup_token.php" class="inline-form compact">
            <button type="submit" class="btn btn-primary">Generate signup link</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($users): ?>
    <div class="file-list file-list-users">
        <div class="file-row-user-management file-header">
            <div>Registered</div>
            <div>Username</div>
            <div>Email</div>
            <div>Files</div>
            <div>Storage</div>
            <div>Partition</div>
            <div>Role</div>
            <div>Reset password</div>
            <div>Delete</div>
        </div>

        <?php foreach ($users as $user): ?>
            <div class="file-row-user-management">
                <div><?= format_datetime_display($user['created_at']) ?></div>
                <div><?= user_profile_link($user['username']) ?></div>
                <div><?= sanitize_data($user['email']) ?></div>
                <div><?= sanitize_data($user['file_count']) ?></div>
                <div><?= format_filesize($user['total_size']) ?></div>
                <div>
                    <form method="post" action="admin_sections/admin_set_user_partition.php" class="inline-form compact">
                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                        <select name="storage_partition_id" aria-label="Storage partition" class="select-compact">
                            <option value="">Default</option>
                            <?php foreach ($storagePartitionsList ?? [] as $sp): ?>
                                <option value="<?= (int) $sp['id'] ?>"<?= ((int) ($user['storage_partition_id'] ?? 0) === (int) $sp['id']) ? ' selected' : '' ?>><?= sanitize_data($sp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-small">Save</button>
                    </form>
                </div>
                <div>
                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                        <form method="post" action="admin_sections/admin_change_role.php" class="inline-form compact" onsubmit="return confirm('Change this user’s role?')">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <select name="role">
                                <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-small">Update</button>
                        </form>
                    <?php else: ?>
                        <?= sanitize_data($user['role']) ?> (You)
                    <?php endif; ?>
                </div>
                <div>
                    <form method="post" action="admin_sections/admin_generate_password_reset.php" class="inline-form compact">
                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                        <button type="submit" class="btn btn-quiet btn-small">Copy reset link</button>
                    </form>
                </div>
                <div>
                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                        <form method="post" action="admin_sections/admin_delete_user.php" onsubmit="return confirm('Delete this user?');">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-small">Delete</button>
                        </form>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <p>No users found.</p>
    </div>
<?php endif; ?>
