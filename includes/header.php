<?php
$settingsPath = __DIR__ . '/../config/settings.php';
$dbPath = __DIR__ . '/../config/db.php';

$currentScript = basename($_SERVER['SCRIPT_NAME']);
if ($currentScript !== 'install.php' && (!file_exists($settingsPath) || !file_exists($dbPath))) {
    header("Location: install.php");
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
if (file_exists($settingsPath)) require_once $settingsPath;

$siteName = get_site_name();
$pageTitle = $pageTitle ?? $siteName;
$currentPage = get_current_page();
$guestUploadsEnabled = $settings['guest_uploads']['enabled'] ?? false;
$theme = $settings['theme'] ?? 'default';

$themeMap = [
    'default' => 'themes/light.css',
    'light' => 'themes/light.css',
];
$themePath = $themeMap[$theme] ?? 'themes/light.css';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= sanitize_data($pageTitle) ?> | <?= sanitize_data($siteName) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/<?= sanitize_data($themePath) ?>">
</head>
<body>
    <div class="page-wrapper">
        <header class="site-header">
            <div class="header-inner">
                <div class="site-title">
                    <a href="index.php"><?= sanitize_data($siteName) ?></a>
                </div>
                <nav class="main-nav">
                    <a href="index.php"<?= $currentPage === 'index.php' ? ' class="active"' : '' ?>>Home</a>
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <a href="dashboard.php"<?= $currentPage === 'dashboard.php' ? ' class="active"' : '' ?>>Your Files</a>
                        <a href="upload.php"<?= $currentPage === 'upload.php' ? ' class="active"' : '' ?>>Upload</a>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="admin.php"<?= $currentPage === 'admin.php' ? ' class="active"' : '' ?>>Admin Panel</a>
                        <?php endif; ?>
                        <a href="logout.php">Logout (<?= sanitize_data($_SESSION['username']) ?>)</a>
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

        <main class="container">

            <?php
            if (!empty($_SESSION['user_id']) && $_SESSION['role'] === 'admin' && file_exists(__DIR__ . '/../install.php')) {
                echo '
                <div class="flash warning persistent">
                    <button class="close-btn" onclick="this.parentElement.style.display=\'none\'">✖</button>
                    <div>
                        ⚠️ <strong>Security Warning:</strong> <code>install.php</code> still exists on your server.<br>
                        For security, please <strong>delete or rename this file immediately</strong>.
                    </div>
                </div>';
            }
            ?>

            <?php foreach (['success', 'error', 'warning'] as $type): ?>
                <?php if (!empty($_SESSION["flash_$type"])): ?>
                    <?php foreach ((array)$_SESSION["flash_$type"] as $msg): ?>
                        <div class="flash <?= $type ?>">
                            <button class="close-btn" onclick="this.parentElement.remove()">✖</button>
                            <?= is_array($msg) && !empty($msg['html']) ? $msg['msg'] : sanitize_data($msg) ?>
                        </div>
                    <?php endforeach; unset($_SESSION["flash_$type"]); ?>
                <?php endif; ?>
            <?php endforeach; ?>
