<?php
// Load settings if not already loaded
if (!isset($settings)) {
    require_once __DIR__ . '/../config/settings.php';
}
if (!function_exists('sanitize_data')) {
    require_once __DIR__ . '/../includes/functions.php';
}

$siteName = get_site_name();
?>
        </main> <!-- End .container -->
    </div> <!-- End .page-wrapper -->

    <footer>
        <p>&copy; <?= date('Y') ?> <?= sanitize_data($siteName) ?>. All rights reserved.</p>
    </footer>
</body>
</html>