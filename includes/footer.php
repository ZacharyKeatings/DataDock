<?php
// Load settings if not already loaded
if (!isset($settings)) {
    require_once __DIR__ . '/../config/settings.php';
}
if (!function_exists('sanitize_data')) {
    require_once __DIR__ . '/../includes/functions.php';
}

$siteName = get_site_name();
$version = @file_get_contents(__DIR__ . '/../VERSION');
?>
        </main> <!-- End .container -->
    </div> <!-- End .page-wrapper -->

    <footer>
        <p>
            &copy; <?= date('Y') ?> 
            <?= sanitize_data($siteName) ?>. All rights reserved. 
            <?php if ($version): ?>
                v<?= sanitize_data($version) ?> DataDock
            <?php endif; ?>
        </p>

    </footer>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.flash:not(.persistent)').forEach(msg => {
            setTimeout(() => {
                msg.style.transition = "opacity 1s ease, margin 1s ease, height 1s ease, padding 1s ease";
                msg.style.opacity = 0;
                msg.style.margin = "0";
                msg.style.padding = "0";
                msg.style.height = "0";
                msg.style.overflow = "hidden";

                setTimeout(() => msg.remove(), 1100);
            }, 5000);
        });
    });
    </script>
</body>
</html>
