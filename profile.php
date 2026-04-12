<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
require_once __DIR__ . '/includes/read_only.php';

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, username, email, display_name, avatar, bio, role, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    $_SESSION['flash_error'][] = "Account not found.";
    header("Location: dashboard.php");
    exit;
}

$username = $user['username'];
$email = $user['email'];
$displayName = $user['display_name'] ?? '';
$avatar = $user['avatar'] ?? '';
$bio = $user['bio'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        if (datadock_read_only_enabled($settings)) {
            $_SESSION['flash_error'][] = '❌ Read-only mode: profile details cannot be changed (password change below still works).';
            header('Location: profile.php');
            exit;
        }
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $removeAvatar = !empty($_POST['remove_avatar']);
        $avatarUrl = trim($_POST['avatar_url'] ?? '');

        if (strlen($username) < 3) {
            $_SESSION['flash_error'][] = "❌ Username must be at least 3 characters.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'][] = "❌ Invalid email address.";
        }
        if (strlen($displayName) > 100) {
            $_SESSION['flash_error'][] = "❌ Display name must be 100 characters or less.";
        }
        if (strlen($bio) > 500) {
            $_SESSION['flash_error'][] = "❌ Bio must be 500 characters or less.";
        }
        if ($avatarUrl !== '' && !filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
            $_SESSION['flash_error'][] = "❌ Avatar URL must be a valid URL.";
        }
        if ($avatarUrl !== '' && !preg_match('#^https?://#i', $avatarUrl)) {
            $_SESSION['flash_error'][] = "❌ Avatar URL must start with http:// or https://.";
        }

        $newAvatar = null;
        if (!empty($_SESSION['flash_error'])) {
            // keep current for re-display
        } elseif ($removeAvatar) {
            $newAvatar = '';
            $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $old = $stmt->fetchColumn();
            if ($old && strpos($old, 'http') !== 0) {
                $oldPath = get_avatars_path() . basename($old);
                if (file_exists($oldPath)) @unlink($oldPath);
            }
        } elseif (!empty($_FILES['avatar_upload']['tmp_name']) && is_uploaded_file($_FILES['avatar_upload']['tmp_name'])) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['avatar_upload']['tmp_name']);
            $allowed = ['image/jpeg' => true, 'image/png' => true, 'image/gif' => true, 'image/webp' => true];
            $maxSize = 2 * 1024 * 1024;
            if (!isset($allowed[$mime])) {
                $_SESSION['flash_error'][] = "❌ Avatar must be JPEG, PNG, GIF, or WebP.";
            } elseif ($_FILES['avatar_upload']['size'] > $maxSize) {
                $_SESSION['flash_error'][] = "❌ Avatar must be 2 MB or smaller.";
            } else {
                $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
                $dir = get_avatars_path();
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $filename = $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar_upload']['tmp_name'], $dir . $filename)) {
                    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $old = $stmt->fetchColumn();
                    if ($old && strpos($old, 'http') !== 0) {
                        $oldPath = $dir . basename($old);
                        if (file_exists($oldPath)) @unlink($oldPath);
                    }
                    $newAvatar = $filename;
                } else {
                    $_SESSION['flash_error'][] = "❌ Could not save avatar upload.";
                }
            }
        } elseif ($avatarUrl !== '') {
            $newAvatar = $avatarUrl;
            $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $old = $stmt->fetchColumn();
            if ($old && strpos($old, 'http') !== 0) {
                $oldPath = get_avatars_path() . basename($old);
                if (file_exists($oldPath)) @unlink($oldPath);
            }
        }

        if (empty($_SESSION['flash_error'])) {
            $enforceUniqueEmail = $settings['enforce_unique_email'] ?? true;
            if ($enforceUniqueEmail) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $userId]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $userId]);
            }
            if ($stmt->fetch()) {
                $_SESSION['flash_error'][] = $enforceUniqueEmail ? "❌ Username or email already in use." : "❌ Username already in use.";
            }
        }

        if (empty($_SESSION['flash_error'])) {
            $avatarValue = ($newAvatar !== null) ? ($newAvatar === '' ? null : $newAvatar) : (trim($avatar) === '' ? null : $avatar);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, display_name = ?, avatar = ?, bio = ? WHERE id = ?");
            $stmt->execute([$username, $email, $displayName ?: null, $avatarValue, $bio ?: null, $userId]);
            $_SESSION['username'] = $username;
            $avatar = $avatarValue ?? '';
            $_SESSION['flash_success'][] = "✅ Profile updated.";
            header("Location: profile.php");
            exit;
        }
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!password_verify($current, $row['password_hash'])) {
            $_SESSION['flash_error'][] = "❌ Current password is incorrect.";
        } elseif (strlen($newPass) < 6) {
            $_SESSION['flash_error'][] = "❌ New password must be at least 6 characters.";
        } elseif ($newPass !== $confirm) {
            $_SESSION['flash_error'][] = "❌ New passwords do not match.";
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $userId]);
            $_SESSION['flash_success'][] = "✅ Password changed.";
            header("Location: profile.php");
            exit;
        }
    }
}

// --- Profile statistics (current = non-expired only) ---
$statsCurrent = $pdo->prepare("
    SELECT COUNT(*) AS file_count,
           COALESCE(SUM(filesize), 0) AS total_size,
           COALESCE(SUM(download_count), 0) AS total_downloads,
           COALESCE(AVG(filesize), 0) AS avg_size,
           MIN(upload_date) AS oldest_upload,
           MAX(upload_date) AS newest_upload
    FROM files
    WHERE user_id = ? AND deleted_at IS NULL AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())
");
$statsCurrent->execute([$userId]);
$current = $statsCurrent->fetch(PDO::FETCH_ASSOC);

$statsAll = $pdo->prepare("
    SELECT COUNT(*) AS file_count,
           COALESCE(SUM(filesize), 0) AS total_size,
           COALESCE(SUM(download_count), 0) AS total_downloads
    FROM files WHERE user_id = ? AND deleted_at IS NULL
");
$statsAll->execute([$userId]);
$allTime = $statsAll->fetch(PDO::FETCH_ASSOC);

$expiredCount = $pdo->prepare("SELECT COUNT(*) FROM files WHERE user_id = ? AND deleted_at IS NULL AND expiry_date IS NOT NULL AND expiry_date <= UTC_TIMESTAMP()");
$expiredCount->execute([$userId]);
$expiredFilesCount = (int) $expiredCount->fetchColumn();

$publicCount = $pdo->prepare("SELECT COUNT(*) FROM files WHERE user_id = ? AND is_public = 1 AND deleted_at IS NULL AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())");
$publicCount->execute([$userId]);
$publicFilesCount = (int) $publicCount->fetchColumn();

$sharedWithMe = $pdo->prepare("SELECT COUNT(*) FROM file_shares WHERE shared_with_user_id = ?");
$sharedWithMe->execute([$userId]);
$sharedWithMeCount = (int) $sharedWithMe->fetchColumn();

$sharedByMe = $pdo->prepare("SELECT COUNT(*) FROM file_shares WHERE shared_by_user_id = ?");
$sharedByMe->execute([$userId]);
$sharedByMeCount = (int) $sharedByMe->fetchColumn();

// Most common file type (current files) — by MIME then by extension
$topTypes = $pdo->prepare("
    SELECT filetype, COUNT(*) AS cnt, COALESCE(SUM(filesize), 0) AS size
    FROM files
    WHERE user_id = ? AND deleted_at IS NULL AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP()) AND (filetype IS NOT NULL AND filetype != '')
    GROUP BY filetype
    ORDER BY cnt DESC
    LIMIT 10
");
$topTypes->execute([$userId]);
$topFileTypes = $topTypes->fetchAll(PDO::FETCH_ASSOC);

// By extension (from original_name) for current files
$extStmt = $pdo->prepare("
    SELECT original_name FROM files
    WHERE user_id = ? AND deleted_at IS NULL AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP()) AND original_name IS NOT NULL AND original_name != ''
");
$extStmt->execute([$userId]);
$extCounts = [];
while ($row = $extStmt->fetch(PDO::FETCH_ASSOC)) {
    $ext = strtolower(pathinfo($row['original_name'], PATHINFO_EXTENSION));
    if ($ext !== '') {
        $extCounts[$ext] = ($extCounts[$ext] ?? 0) + 1;
    }
}
arsort($extCounts);
$topExtensions = array_slice($extCounts, 0, 10, true);

$profileIncomplete = (trim($displayName) === '' && trim($bio) === '' && trim($avatar) === '');

$pageTitle = "Profile";
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section auth-form">
    <h2 class="page-title">Your profile</h2>

    <?php if ($profileIncomplete): ?>
    <div class="flash warning" role="alert">
        <strong>Complete your profile:</strong> Add a display name, bio, or profile picture to personalize your <a href="user.php?username=<?= urlencode($user['username']) ?>">public profile</a>.
    </div>
    <?php endif; ?>

    <div class="settings-card profile-stats-card" style="margin-bottom: 1.5rem;">
        <h3 class="settings-card-title">Your statistics</h3>
        <div class="settings-card-body profile-stats-grid">
            <div class="profile-stat">
                <span class="profile-stat-value"><?= (int) $current['file_count'] ?></span>
                <span class="profile-stat-label">Files currently stored</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><?= format_filesize((int) $current['total_size']) ?></span>
                <span class="profile-stat-label">Storage used (current)</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><?= (int) $allTime['file_count'] ?></span>
                <span class="profile-stat-label">Total files (all time in system)</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><?= format_filesize((int) $allTime['total_size']) ?></span>
                <span class="profile-stat-label">Total size (all in system)</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><?= $expiredFilesCount ?></span>
                <span class="profile-stat-label">Expired files (no longer counted)</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><?= number_format((int) $current['total_downloads']) ?></span>
                <span class="profile-stat-label">Total downloads (your files)</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><?= $publicFilesCount ?></span>
                <span class="profile-stat-label">Public files (current)</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><?= format_filesize((int) $current['avg_size']) ?></span>
                <span class="profile-stat-label">Average file size (current)</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><?= $sharedWithMeCount ?></span>
                <span class="profile-stat-label">Files shared with you</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><?= $sharedByMeCount ?></span>
                <span class="profile-stat-label">Files you’ve shared with others</span>
            </div>
            <?php if ($current['oldest_upload']): ?>
            <div class="profile-stat">
                <span class="profile-stat-value"><?= format_datetime_display($current['oldest_upload']) ?></span>
                <span class="profile-stat-label">Oldest upload (current)</span>
            </div>
            <?php endif; ?>
            <?php if ($current['newest_upload']): ?>
            <div class="profile-stat">
                <span class="profile-stat-value"><?= format_datetime_display($current['newest_upload']) ?></span>
                <span class="profile-stat-label">Newest upload (current)</span>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($topFileTypes) || !empty($topExtensions)): ?>
        <div class="settings-card-body" style="border-top: 1px solid var(--color-border, #ccc); margin-top: 0.5rem; padding-top: 1rem;">
            <?php if (!empty($topFileTypes)): ?>
            <h4 class="profile-stats-subtitle">Most common file types (by MIME)</h4>
            <ul class="profile-stats-list">
                <?php foreach ($topFileTypes as $i => $row): ?>
                <li><strong><?= sanitize_data(get_friendly_filetype($row['filetype'])) ?></strong> — <?= (int) $row['cnt'] ?> file<?= (int) $row['cnt'] !== 1 ? 's' : '' ?>, <?= format_filesize((int) $row['size']) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if (!empty($topExtensions)): ?>
            <h4 class="profile-stats-subtitle">Most common extensions</h4>
            <ul class="profile-stats-list">
                <?php foreach ($topExtensions as $ext => $cnt): ?>
                <li><strong>.<?= sanitize_data($ext) ?></strong> — <?= $cnt ?> file<?= $cnt !== 1 ? 's' : '' ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="settings-card" style="margin-bottom: 1.5rem;">
        <h3 class="settings-card-title">Account details</h3>
        <div class="settings-card-body">
            <p><strong>Role:</strong> <?= sanitize_data($user['role']) ?></p>
            <p><strong>Member since:</strong> <?= format_datetime_display($user['created_at']) ?></p>
        </div>
    </div>

    <div class="settings-card" style="margin-bottom: 1.5rem;">
        <h3 class="settings-card-title">Edit profile</h3>
        <div class="settings-card-body">
            <form method="post" class="form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="profile">
                <div class="form-group profile-avatar-group">
                    <label>Profile picture</label>
                    <?php if (trim($avatar) !== ''): ?>
                    <div class="profile-avatar-preview">
                        <?php if (preg_match('#^https?://#i', $avatar)): ?>
                        <img src="<?= sanitize_data($avatar) ?>" alt="Avatar" class="profile-avatar-img" loading="lazy">
                        <?php else: ?>
                        <img src="avatar.php?id=<?= (int) $userId ?>" alt="Avatar" class="profile-avatar-img" loading="lazy">
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="avatar_url">Avatar URL</label>
                        <input type="url" name="avatar_url" id="avatar_url" value="<?= preg_match('#^https?://#i', $avatar) ? sanitize_data($avatar) : '' ?>" placeholder="https://…">
                    </div>
                    <div class="form-group">
                        <label for="avatar_upload">Or upload image (JPEG, PNG, GIF, WebP; max 2 MB)</label>
                        <input type="file" name="avatar_upload" id="avatar_upload" accept="image/jpeg,image/png,image/gif,image/webp">
                    </div>
                    <?php if (trim($avatar) !== ''): ?>
                    <div class="form-group settings-row-checkbox">
                        <label>
                            <input type="checkbox" name="remove_avatar" value="1"> Remove profile picture
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="profile_username">Username</label>
                    <input type="text" name="username" id="profile_username" value="<?= sanitize_data($username) ?>" required minlength="3">
                </div>
                <div class="form-group">
                    <label for="profile_email">Email</label>
                    <input type="email" name="email" id="profile_email" value="<?= sanitize_data($email) ?>" required>
                </div>
                <div class="form-group">
                    <label for="profile_display_name">Display name (optional)</label>
                    <input type="text" name="display_name" id="profile_display_name" value="<?= sanitize_data($displayName) ?>" maxlength="100" placeholder="How you want to be shown">
                </div>
                <div class="form-group">
                    <label for="profile_bio">Bio (optional, max 500 characters)</label>
                    <textarea name="bio" id="profile_bio" maxlength="500" rows="4" placeholder="A short description for your public profile"><?= sanitize_data($bio) ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save profile</button>
            </form>
        </div>
    </div>

    <div class="settings-card">
        <h3 class="settings-card-title">Change password</h3>
        <div class="settings-card-body">
            <form method="post" class="form" onsubmit="return validatePasswordForm()">
                <input type="hidden" name="action" value="password">
                <div class="form-group">
                    <label for="current_password">Current password</label>
                    <input type="password" name="current_password" id="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New password</label>
                    <input type="password" name="new_password" id="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm new password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary">Change password</button>
            </form>
            <script>
            function validatePasswordForm() {
                var a = document.getElementById('new_password').value;
                var b = document.getElementById('confirm_password').value;
                if (a !== b) {
                    alert('New passwords do not match.');
                    return false;
                }
                return true;
            }
            </script>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
