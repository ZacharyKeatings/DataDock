<?php
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/functions.php';

$siteName = get_site_name();
$pageTitle = $pageTitle ?? $siteName;
$currentPage = get_current_page();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= sanitize_data($pageTitle) ?> | <?= sanitize_data($siteName) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header>
        <nav>
            <strong><?= sanitize_data($siteName) ?></strong> |
            <a href="index.php"<?= $currentPage === 'index.php' ? ' class="active"' : '' ?>>Home</a>
            <?php if (!empty($_SESSION['user_id'])): ?>
                <a href="dashboard.php"<?= $currentPage === 'dashboard.php' ? ' class="active"' : '' ?>>Your Files</a>
                <a href="upload.php"<?= $currentPage === 'upload.php' ? ' class="active"' : '' ?>>Upload</a>

                <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="admin.php"<?= $currentPage === 'admin.php' ? ' class="active"' : '' ?>>Admin Panel</a>
                <?php endif; ?>

                <a href="logout.php">Logout (<?= sanitize_data($_SESSION['username']) ?>)</a>
            <?php else: ?>
                <a href="login.php"<?= $currentPage === 'login.php' ? ' class="active"' : '' ?>>Login</a>
                <a href="register.php"<?= $currentPage === 'register.php' ? ' class="active"' : '' ?>>Register</a>
            <?php endif; ?>
        </nav>
    </header>
    <div class="container">
    <?php
    $installPath = __DIR__ . '/../install.php';
    if (file_exists($installPath)) {
        echo '
        <div class="warning">
            <button class="close-btn" onclick="this.parentElement.style.display=\'none\'">✖</button>
            <div>
            ⚠️ <strong>Security Warning:</strong> <code>install.php</code> still exists on your server.<br>
            For security, please <strong>delete or rename this file immediately</strong> to prevent unauthorized access.
            </div>
        </div>';
    }
    ?>
