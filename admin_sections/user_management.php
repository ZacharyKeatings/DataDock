<?php
require_once __DIR__ . '/../includes/accounts.php';
$userDirectory = $userDirectory ?? $users;
?>
<h2 class="page-title">User Management</h2>

<div class="settings-card stack-gap">
    <h3 class="settings-card-title">Create account</h3>
    <div class="settings-card-body">
        <form method="post" action="admin_sections/admin_create_user.php" class="form">
            <div class="form-group">
                <label for="new_username">Username</label>
                <input type="text" name="username" id="new_username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+">
            </div>
            <div class="form-group">
                <label for="new_email">Email</label>
                <input type="email" name="email" id="new_email" required>
            </div>
            <div class="form-group">
                <label for="new_password">Password</label>
                <input type="password" name="password" id="new_password" required minlength="<?= (int) datadock_password_policy($settings)['min_length'] ?>">
                <p class="form-hint"><?= sanitize_data(datadock_password_hint($settings)) ?></p>
            </div>
            <div class="form-group">
                <label for="new_role">Role</label>
                <select name="role" id="new_role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Create account</button>
        </form>
    </div>
</div>

<?php if ($userDirectory): ?>
<?php
$quotaForm = [];
foreach ($userDirectory as $user) {
    $filesRaw = $user['quota_max_files'] ?? null;
    $bytesRaw = $user['quota_max_bytes'] ?? null;
    $filesInt = ($filesRaw === null || $filesRaw === '') ? null : (int) $filesRaw;
    $bytesInt = ($bytesRaw === null || $bytesRaw === '') ? null : (int) $bytesRaw;
    if ($filesInt === null && $bytesInt === null) {
        $mode = 'inherit';
        $files = 100;
        $amount = 100;
        $unit = 'm';
    } elseif (($filesInt !== null && $filesInt < 0) || ($bytesInt !== null && $bytesInt < 0)) {
        $mode = 'unlimited';
        $files = 100;
        $amount = 100;
        $unit = 'm';
    } else {
        $mode = 'custom';
        $files = max(0, (int) $filesInt);
        [$amount, $unit] = bytes_to_display(max(0, (int) $bytesInt));
    }
    $quotaForm[(int) $user['id']] = [
        'mode' => $mode,
        'files' => $files,
        'amount' => $amount,
        'unit' => $unit,
    ];
}
$quotaFirst = $quotaForm[(int) $userDirectory[0]['id']];
?>
<div class="settings-card stack-gap">
    <h3 class="settings-card-title">Storage quota</h3>
    <div class="settings-card-body">
        <p class="settings-hint">Site default uses the limits from Site Settings. Unlimited ignores those limits for this account. Custom sets a file count and a total size for this account only.</p>
        <form method="post" action="admin_sections/admin_set_quota.php" class="form">
            <div class="form-group">
                <label for="quota_user_id">Account</label>
                <select name="user_id" id="quota_user_id">
                    <?php foreach ($userDirectory as $user): ?>
                        <?php $choice = $quotaForm[(int) $user['id']]; ?>
                        <option value="<?= (int) $user['id'] ?>" data-mode="<?= sanitize_data($choice['mode']) ?>" data-files="<?= sanitize_data((string) $choice['files']) ?>" data-amount="<?= sanitize_data((string) $choice['amount']) ?>" data-unit="<?= sanitize_data($choice['unit']) ?>"><?= sanitize_data($user['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="quota_mode">Limit</label>
                <select name="quota_mode" id="quota_mode">
                    <option value="inherit"<?= $quotaFirst['mode'] === 'inherit' ? ' selected' : '' ?>>Site default</option>
                    <option value="unlimited"<?= $quotaFirst['mode'] === 'unlimited' ? ' selected' : '' ?>>Unlimited</option>
                    <option value="custom"<?= $quotaFirst['mode'] === 'custom' ? ' selected' : '' ?>>Custom</option>
                </select>
            </div>
            <div class="form-group">
                <label for="max_files">Max files</label>
                <input type="number" name="max_files" id="max_files" min="0" value="<?= sanitize_data((string) $quotaFirst['files']) ?>">
            </div>
            <div class="form-group">
                <label for="max_bytes">Max storage</label>
                <input type="number" name="max_bytes" id="max_bytes" min="0" step="any" value="<?= sanitize_data((string) $quotaFirst['amount']) ?>">
                <select name="max_bytes_unit" id="max_bytes_unit" aria-label="Storage unit">
                    <option value="m"<?= $quotaFirst['unit'] === 'm' ? ' selected' : '' ?>>MB</option>
                    <option value="k"<?= $quotaFirst['unit'] === 'k' ? ' selected' : '' ?>>KB</option>
                    <option value="g"<?= $quotaFirst['unit'] === 'g' ? ' selected' : '' ?>>GB</option>
                    <option value="b"<?= $quotaFirst['unit'] === 'b' ? ' selected' : '' ?>>B</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Save quota</button>
        </form>
        <script>
        (function () {
            var account = document.getElementById('quota_user_id');
            var mode = document.getElementById('quota_mode');
            var files = document.getElementById('max_files');
            var bytes = document.getElementById('max_bytes');
            var unit = document.getElementById('max_bytes_unit');
            if (!account || !mode || !files || !bytes || !unit) return;
            function apply() {
                var opt = account.options[account.selectedIndex];
                if (!opt) return;
                mode.value = opt.getAttribute('data-mode') || 'inherit';
                files.value = opt.getAttribute('data-files') || '100';
                bytes.value = opt.getAttribute('data-amount') || '100';
                unit.value = opt.getAttribute('data-unit') || 'm';
            }
            account.addEventListener('change', apply);
        })();
        </script>
    </div>
</div>
<?php endif; ?>

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

<?php if ($userDirectory): ?>
<div class="settings-card stack-gap">
    <h3 class="settings-card-title">Transfer files</h3>
    <div class="settings-card-body">
        <p class="settings-hint">Move every file from one account to another before you delete someone. Bytes are copied when the accounts use different storage partitions. Shares and links stay on the same file ids.</p>
        <form method="post" action="admin_sections/admin_transfer_files.php" class="form" onsubmit="return confirm('Move all files to the other account?');">
            <div class="form-group">
                <label for="source_user_id">From</label>
                <select name="source_user_id" id="source_user_id">
                    <?php foreach ($userDirectory as $user): ?>
                        <option value="<?= (int) $user['id'] ?>"><?= sanitize_data($user['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="target_user_id">To</label>
                <select name="target_user_id" id="target_user_id">
                    <?php foreach ($userDirectory as $user): ?>
                        <option value="<?= (int) $user['id'] ?>"><?= sanitize_data($user['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Transfer files</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($userDirectory): ?>
    <div class="file-list file-list-users">
        <div class="file-row-user-management file-header">
            <div>Registered</div>
            <div>Username</div>
            <div>Email</div>
            <div>Files</div>
            <div>Storage</div>
            <div>Partition</div>
            <div>Role</div>
            <div>Access</div>
            <div>Reset password</div>
            <div>Delete</div>
        </div>

        <?php foreach ($users as $user): ?>
            <div class="file-row-user-management">
                <div data-label="Registered"><?= format_datetime_display($user['created_at']) ?></div>
                <div data-label="Username"><?= user_profile_link($user['username']) ?></div>
                <div data-label="Email">
                    <?= sanitize_data($user['email']) ?>
                    <?php if (isset($user['email_verified']) && (int) $user['email_verified'] === 0): ?>
                        <span class="badge">Unverified</span>
                        <form method="post" action="admin_sections/admin_confirm_email.php" class="inline-form compact">
                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                            <button type="submit" class="btn btn-quiet btn-small">Confirm email</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div data-label="Files"><?= sanitize_data($user['file_count']) ?></div>
                <div data-label="Storage"><?= format_filesize($user['total_size']) ?></div>
                <div data-label="Partition">
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
                <div data-label="Role">
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
                <div data-label="Access">
                    <?php if (!empty($user['suspended'])): ?>
                        <span class="badge">Suspended</span>
                    <?php endif; ?>
                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                        <form method="post" action="admin_sections/admin_suspend_user.php" class="inline-form compact" onsubmit="return confirm('<?= !empty($user['suspended']) ? 'Allow this account to log in again?' : 'Suspend this account? Login and new shares will be blocked.' ?>');">
                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                            <input type="hidden" name="suspended" value="<?= !empty($user['suspended']) ? '0' : '1' ?>">
                            <button type="submit" class="btn btn-small<?= !empty($user['suspended']) ? '' : ' btn-quiet' ?>"><?= !empty($user['suspended']) ? 'Unsuspend' : 'Suspend' ?></button>
                        </form>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
                <div data-label="Reset password">
                    <form method="post" action="admin_sections/admin_generate_password_reset.php" class="inline-form compact">
                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                        <button type="submit" class="btn btn-quiet btn-small">Copy reset link</button>
                    </form>
                </div>
                <div data-label="Delete">
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
    <?php if (!empty($userPager)) { datadock_render_pager($userPager); } ?>
<?php else: ?>
    <div class="empty-state">
        <p>No users found.</p>
    </div>
<?php endif; ?>
