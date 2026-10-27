<?php
$settingsPath = __DIR__ . '/../config/settings.php';
$dbPath = __DIR__ . '/../config/db.php';

$currentScript = basename($_SERVER['SCRIPT_NAME']);
if ($currentScript !== 'install.php' && (!file_exists($settingsPath) || !file_exists($dbPath))) {
    header("Location: install.php");
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/settings_loader.php';
require_once __DIR__ . '/pastes.php';
$settings = file_exists($settingsPath) ? datadock_load_settings() : [];
require_once __DIR__ . '/security_headers.php';
datadock_send_response_security_headers($settings);
$readOnlyMode = datadock_read_only_enabled($settings);
$pastesEnabled = datadock_pastes_enabled($settings);

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
$themeCssUrl = app_asset_url('assets/' . $themePath);
$loggedIn = !empty($_SESSION['user_id']);
$isAdmin = $loggedIn && (($_SESSION['role'] ?? '') === 'admin');
$homeHref = $loggedIn ? 'dashboard.php' : 'index.php';
$noticeUnread = 0;
if ($loggedIn && isset($pdo) && $pdo instanceof PDO) {
    require_once __DIR__ . '/notices.php';
    $noticeUnread = datadock_notice_unread_count($pdo, (int) $_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= sanitize_data($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= sanitize_data($pageTitle) ?> | <?= sanitize_data($siteName) ?></title>
    <?php if (!empty($faviconUrl)): ?>
    <link rel="icon" href="<?= sanitize_data($faviconUrl) ?>" type="image/x-icon">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= sanitize_data(app_asset_url('assets/style.css')) ?>">
    <link id="theme-stylesheet" rel="stylesheet" href="<?= sanitize_data($themeCssUrl) ?>">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <div class="page-wrapper">
        <header class="site-header" role="banner">
            <div class="header-inner">
                <div class="site-title">
                    <a href="<?= sanitize_data($homeHref) ?>" aria-label="<?= sanitize_data($siteName) ?> home">
                        <?php if (!empty($logoUrl)): ?>
                        <img src="<?= sanitize_data($logoUrl) ?>" alt="<?= sanitize_data($siteName) ?>" class="site-logo">
                        <?php else: ?>
                        <?= sanitize_data($siteName) ?>
                        <?php endif; ?>
                    </a>
                </div>
                <nav class="main-nav" aria-label="Main navigation">
                    <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="site-menu" aria-label="Open menu">
                        <span class="nav-toggle-bar"></span>
                        <span class="nav-toggle-bar"></span>
                        <span class="nav-toggle-bar"></span>
                    </button>
                    <div class="nav-menu" id="site-menu">
                    <?php if ($loggedIn): ?>
                        <a class="nav-link<?= $currentPage === 'dashboard.php' ? ' active' : '' ?>" href="dashboard.php">Files</a>
                        <?php if (empty($readOnlyMode)): ?>
                        <a class="nav-link nav-link-upload<?= $currentPage === 'upload.php' ? ' active' : '' ?>" href="upload.php">Upload</a>
                        <?php endif; ?>
                        <details class="nav-account">
                            <summary class="nav-account-summary">
                                <?= sanitize_data($_SESSION['username'] ?? 'Account') ?>
                                <span class="nav-caret" aria-hidden="true">▾</span>
                            </summary>
                            <div class="nav-account-menu">
                                <a href="profile.php"<?= $currentPage === 'profile.php' ? ' class="active"' : '' ?>>Profile</a>
                                <a href="notices.php"<?= $currentPage === 'notices.php' ? ' class="active"' : '' ?>>Notices<?php if ($noticeUnread > 0): ?> (<?= (int) $noticeUnread ?>)<?php endif; ?></a>
                                <a href="activity.php"<?= $currentPage === 'activity.php' ? ' class="active"' : '' ?>>Activity</a>
                                <a href="trash.php"<?= $currentPage === 'trash.php' ? ' class="active"' : '' ?>>Trash</a>
                                <?php if (!empty($pastesEnabled)): ?>
                                <a href="paste.php"<?= $currentPage === 'paste.php' ? ' class="active"' : '' ?>>Paste</a>
                                <?php endif; ?>
                                <?php if ($isAdmin): ?>
                                <a href="admin.php"<?= $currentPage === 'admin.php' ? ' class="active"' : '' ?>>Admin</a>
                                <?php endif; ?>
                                <div class="nav-theme" role="group" aria-label="Color theme">
                                    <a href="set_theme.php?theme=light" class="theme-btn<?= $theme === 'light' ? ' active' : '' ?>" data-theme-choice="light" data-theme-css="<?= sanitize_data(app_asset_url('assets/themes/light.css')) ?>" title="Light mode" aria-label="Switch to light mode"><?= icon_svg('sun') ?></a>
                                    <a href="set_theme.php?theme=dark" class="theme-btn<?= $theme === 'dark' ? ' active' : '' ?>" data-theme-choice="dark" data-theme-css="<?= sanitize_data(app_asset_url('assets/themes/dark.css')) ?>" title="Dark mode" aria-label="Switch to dark mode"><?= icon_svg('moon') ?></a>
                                </div>
                                <a href="logout.php">Logout</a>
                            </div>
                        </details>
                    <?php else: ?>
                        <a class="nav-link<?= $currentPage === 'index.php' ? ' active' : '' ?>" href="index.php">Home</a>
                        <?php if ($guestUploadsEnabled && empty($readOnlyMode)): ?>
                        <a class="nav-link nav-link-upload<?= $currentPage === 'upload.php' ? ' active' : '' ?>" href="upload.php">Upload</a>
                        <?php endif; ?>
                        <?php if (!empty($pastesEnabled) && $guestUploadsEnabled && empty($readOnlyMode)): ?>
                        <a class="nav-link<?= $currentPage === 'paste.php' ? ' active' : '' ?>" href="paste.php">Paste</a>
                        <?php endif; ?>
                        <a class="nav-link<?= $currentPage === 'login.php' ? ' active' : '' ?>" href="login.php">Login</a>
                        <?php if (empty($readOnlyMode)): ?>
                        <a class="nav-link<?= $currentPage === 'register.php' ? ' active' : '' ?>" href="register.php">Register</a>
                        <?php endif; ?>
                        <span class="theme-toggle nav-theme" role="group" aria-label="Color theme">
                            <a href="set_theme.php?theme=light" class="theme-btn<?= $theme === 'light' ? ' active' : '' ?>" data-theme-choice="light" data-theme-css="<?= sanitize_data(app_asset_url('assets/themes/light.css')) ?>" title="Light mode" aria-label="Switch to light mode"><?= icon_svg('sun') ?></a>
                            <a href="set_theme.php?theme=dark" class="theme-btn<?= $theme === 'dark' ? ' active' : '' ?>" data-theme-choice="dark" data-theme-css="<?= sanitize_data(app_asset_url('assets/themes/dark.css')) ?>" title="Dark mode" aria-label="Switch to dark mode"><?= icon_svg('moon') ?></a>
                        </span>
                    <?php endif; ?>
                    </div>
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
                        <?php
                        $isHtml = is_array($msg) && !empty($msg['html']);
                        $text = datadock_plain_flash($isHtml ? (string) ($msg['msg'] ?? '') : (string) $msg);
                        $persistent = $isHtml && (str_contains($text, 'onetimelink') || str_contains($text, 'btn-copy'));
                        ?>
                        <div class="flash <?= $type ?><?= $persistent ? ' persistent' : '' ?>" role="alert" aria-live="polite">
                            <button type="button" class="close-btn" onclick="this.parentElement.remove()" aria-label="Dismiss"><?= icon_svg('close') ?></button>
                            <?= $isHtml ? $text : sanitize_data($text) ?>
                        </div>
                    <?php endforeach; unset($_SESSION["flash_$type"]); ?>
                <?php endif; ?>
            <?php endforeach; ?>
