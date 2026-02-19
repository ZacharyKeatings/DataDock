<?php
/**
 * Public user profile page. No login required.
 * Shows display name (or username), member since, and public stats only.
 */
require_once __DIR__ . '/includes/auth.php';
init_session();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/settings.php';

$usernameParam = trim($_GET['username'] ?? '');
if ($usernameParam === '') {
    $_SESSION['flash_error'][] = 'No user specified.';
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, display_name, created_at FROM users WHERE username = ?");
$stmt->execute([$usernameParam]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    $_SESSION['flash_error'][] = 'User not found.';
    header('Location: index.php');
    exit;
}

$displayName = trim($user['display_name'] ?? '') !== '' ? $user['display_name'] : $user['username'];

// Public stats only: member since, count of current public files
$publicFilesCount = $pdo->prepare("
    SELECT COUNT(*) FROM files
    WHERE user_id = ? AND is_public = 1 AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())
");
$publicFilesCount->execute([$user['id']]);
$publicFileCount = (int) $publicFilesCount->fetchColumn();

$pageTitle = $displayName;
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section auth-form">
    <h2 class="page-title"><?= sanitize_data($displayName) ?></h2>
    <?php if ($displayName !== $user['username']): ?>
    <p class="profile-public-username">@<?= sanitize_data($user['username']) ?></p>
    <?php endif; ?>

    <div class="settings-card" style="margin-bottom: 1.5rem;">
        <h3 class="settings-card-title">Profile</h3>
        <div class="settings-card-body">
            <p><strong>Member since</strong> <?= format_datetime_display($user['created_at']) ?></p>
            <p><strong>Public files</strong> <?= $publicFileCount ?> file<?= $publicFileCount !== 1 ? 's' : '' ?></p>
        </div>
    </div>

    <?php if (!empty($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id']): ?>
    <p><a href="profile.php" class="btn btn-primary">Edit your profile</a></p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
