<?php
// Load site settings if not already loaded
if (!isset($settings)) {
    require_once __DIR__ . '/../config/settings.php';
}
// Load functions if not already loaded
if (!function_exists('sanitize_data')) {
    require_once __DIR__ . '/../includes/functions.php';
}

$siteName = get_site_name();
?>
    </div> <!-- End .container -->
    <footer>
        <p>&copy; <?= date('Y') ?> <?= sanitize_data($siteName) ?>. All rights reserved.</p>
    </footer>
</body>
</html>
