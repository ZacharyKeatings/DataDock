<?php
$settingsPath = __DIR__ . '/../config/settings.php';
$dbPath = __DIR__ . '/../config/db.php';

$currentScript = basename($_SERVER['SCRIPT_NAME']);
if ($currentScript !== 'install.php' && (!file_exists($settingsPath) || !file_exists($dbPath))) {
    header("Location: install.php");
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
if (file_exists($settingsPath)) require $settingsPath;
$settings = $settings ?? [];

$siteName = get_site_name();
$pageTitle = $pageTitle ?? $siteName;
$currentPage = get_current_page();
$guestUploadsEnabled = $settings['guest_uploads']['enabled'] ?? false;
$defaultTheme = $settings['theme'] ?? 'light';
$logoUrl = trim($settings['logo_url'] ?? '');
$faviconUrl = trim($settings['favicon_url'] ?? '');

// User cookie overrides default theme
$theme = $_COOKIE['datadock_theme'] ?? $defaultTheme;
if (!in_array($theme, ['light', 'dark'], true)) {
    $theme = $defaultTheme;
}
$themeMap = ['light' => 'themes/light.css', 'dark' => 'themes/dark.css'];
$themePath = $themeMap[$theme] ?? 'themes/light.css';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= sanitize_data($theme) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= sanitize_data($pageTitle) ?> | <?= sanitize_data($siteName) ?></title>
    <?php if (!empty($faviconUrl)): ?>
    <link rel="icon" href="<?= sanitize_data($faviconUrl) ?>" type="image/x-icon">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/<?= sanitize_data($themePath) ?>">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <div class="page-wrapper">
        <header class="site-header" role="banner">
            <div class="header-inner">
                <div class="site-title">
                    <a href="index.php" aria-label="<?= sanitize_data($siteName) ?> home">
                        <?php if (!empty($logoUrl)): ?>
                        <img src="<?= sanitize_data($logoUrl) ?>" alt="<?= sanitize_data($siteName) ?>" class="site-logo">
                        <?php else: ?>
                        <?= sanitize_data($siteName) ?>
                        <?php endif; ?>
                    </a>
                </div>
                <nav class="main-nav" aria-label="Main navigation">
                    <span class="theme-toggle">
                        <a href="set_theme.php?theme=light" class="theme-btn<?= $theme === 'light' ? ' active' : '' ?>" title="Light mode" aria-label="Switch to light mode"><?= icon_svg('sun') ?></a>
                        <a href="set_theme.php?theme=dark" class="theme-btn<?= $theme === 'dark' ? ' active' : '' ?>" title="Dark mode" aria-label="Switch to dark mode"><?= icon_svg('moon') ?></a>
                    </span>
                    <a href="index.php"<?= $currentPage === 'index.php' ? ' class="active"' : '' ?>>Home</a>
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <a href="dashboard.php"<?= $currentPage === 'dashboard.php' ? ' class="active"' : '' ?>>Your Files</a>
                        <a href="upload.php"<?= $currentPage === 'upload.php' ? ' class="active"' : '' ?>>Upload</a>
                        <a href="profile.php"<?= $currentPage === 'profile.php' ? ' class="active"' : '' ?>>Profile</a>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="admin.php"<?= $currentPage === 'admin.php' ? ' class="active"' : '' ?>>Admin Panel</a>
                        <?php endif; ?>
                        <span class="nav-logout-wrap"><a href="logout.php">Logout</a> (<?= user_profile_link($_SESSION['username']) ?>)</span>
                    <?php else: ?>
                        <?php if ($guestUploadsEnabled): ?>
                            <a href="upload.php"<?= $currentPage === 'upload.php' ? ' class="active"' : '' ?>>Upload</a>
                        <?php endif; ?>
                        <a href="login.php"<?= $currentPage === 'login.php' ? ' class="active"' : '' ?>>Login</a>
                        <a href="register.php"<?= $currentPage === 'register.php' ? ' class="active"' : '' ?>>Register</a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>

        <main id="main-content" class="container" role="main">

            <?php
            $installWarningEnabled = $settings['install_warning_enabled'] ?? true;
            if ($installWarningEnabled && !empty($_SESSION['user_id']) && $_SESSION['role'] === 'admin' && file_exists(__DIR__ . '/../install.php')) {
                echo '
                <div class="flash warning persistent">
                    <button type="button" class="close-btn" onclick="this.parentElement.style.display=\'none\'" aria-label="Dismiss">' . icon_svg('close') . '</button>
                    <div>
                        ' . icon_svg('warning') . ' <strong>Security Warning:</strong> <code>install.php</code> still exists on your server.<br>
                        For security, please <strong>delete or rename this file immediately</strong>.
                    </div>
                </div>';
            }
            ?>

            <?php foreach (['success', 'error', 'warning'] as $type): ?>
                <?php if (!empty($_SESSION["flash_$type"])): ?>
                    <?php foreach ((array)$_SESSION["flash_$type"] as $msg): ?>
                        <div class="flash <?= $type ?>" role="alert" aria-live="polite">
                            <button type="button" class="close-btn" onclick="this.parentElement.remove()" aria-label="Dismiss"><?= icon_svg('close') ?></button>
                            <?= is_array($msg) && !empty($msg['html']) ? $msg['msg'] : sanitize_data($msg) ?>
                        </div>
                    <?php endforeach; unset($_SESSION["flash_$type"]); ?>
                <?php endif; ?>
            <?php endforeach; ?>
