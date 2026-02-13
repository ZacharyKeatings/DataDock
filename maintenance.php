<?php
/**
 * Maintenance mode page. Shown to non-admins when maintenance_mode is enabled.
 */
require_once __DIR__ . '/includes/functions.php';
$settingsPath = __DIR__ . '/config/settings.php';
$siteName = 'DataDock';
if (file_exists($settingsPath)) {
    require $settingsPath;
    $siteName = $settings['site_name'] ?? 'DataDock';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($siteName) ?> — Maintenance</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrapper">
        <div class="page-section" style="max-width: 500px; margin: 4rem auto; text-align: center;">
            <h2 class="page-title">🔧 Site Under Maintenance</h2>
            <p>We're currently performing maintenance. Please try again later.</p>
            <p style="margin-top: 1.5rem;">
                <a href="login.php" class="btn btn-primary">Admin Login</a>
            </p>
        </div>
    </div>
</body>
</html>
