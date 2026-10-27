<?php
/**
 * Maintenance mode page. Shown to non-admins when maintenance_mode is enabled.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$siteName = 'DataDock';
$settings = [];
if (file_exists(__DIR__ . '/config/settings.php')) {
    $settings = datadock_load_settings();
    $siteName = $settings['site_name'] ?? 'DataDock';
}
$theme = $_COOKIE['datadock_theme'] ?? ($settings['theme'] ?? 'light');
if (!in_array($theme, ['light', 'dark'], true)) {
    $theme = 'light';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($siteName) ?> — Maintenance</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_asset_url('assets/style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_asset_url('assets/themes/' . $theme . '.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <div class="page-wrapper">
        <div class="page-section notice-card">
            <h2 class="page-title"><?= icon_svg('wrench') ?> Site under maintenance</h2>
            <p>This site is temporarily unavailable. Please try again later.</p>
            <p class="form-actions notice-actions">
                <a href="login.php" class="btn btn-primary">Admin login</a>
            </p>
        </div>
    </div>
</body>
</html>
