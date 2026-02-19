<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/settings.php';

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, username, email, display_name, role, created_at FROM users WHERE id = ?");
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');

        if (strlen($username) < 3) {
            $_SESSION['flash_error'][] = "❌ Username must be at least 3 characters.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'][] = "❌ Invalid email address.";
        }
        if (strlen($displayName) > 100) {
            $_SESSION['flash_error'][] = "❌ Display name must be 100 characters or less.";
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
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, display_name = ? WHERE id = ?");
            $stmt->execute([$username, $email, $displayName ?: null, $userId]);
            $_SESSION['username'] = $username;
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
    WHERE user_id = ? AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())
");
$statsCurrent->execute([$userId]);
$current = $statsCurrent->fetch(PDO::FETCH_ASSOC);

$statsAll = $pdo->prepare("
    SELECT COUNT(*) AS file_count,
           COALESCE(SUM(filesize), 0) AS total_size,
           COALESCE(SUM(download_count), 0) AS total_downloads
    FROM files WHERE user_id = ?
");
$statsAll->execute([$userId]);
$allTime = $statsAll->fetch(PDO::FETCH_ASSOC);

$expiredCount = $pdo->prepare("SELECT COUNT(*) FROM files WHERE user_id = ? AND expiry_date IS NOT NULL AND expiry_date <= UTC_TIMESTAMP()");
$expiredCount->execute([$userId]);
$expiredFilesCount = (int) $expiredCount->fetchColumn();

$publicCount = $pdo->prepare("SELECT COUNT(*) FROM files WHERE user_id = ? AND is_public = 1 AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())");
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
    WHERE user_id = ? AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP()) AND (filetype IS NOT NULL AND filetype != '')
    GROUP BY filetype
    ORDER BY cnt DESC
    LIMIT 10
");
$topTypes->execute([$userId]);
$topFileTypes = $topTypes->fetchAll(PDO::FETCH_ASSOC);

// By extension (from original_name) for current files
$extStmt = $pdo->prepare("
    SELECT original_name FROM files
    WHERE user_id = ? AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP()) AND original_name IS NOT NULL AND original_name != ''
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

$pageTitle = "Profile";
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section auth-form">
    <h2 class="page-title">Your profile</h2>

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
            <form method="post" class="form">
                <input type="hidden" name="action" value="profile">
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
