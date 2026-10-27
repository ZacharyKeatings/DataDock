<?php
// Load settings if not already loaded
if (!isset($settings)) {
    require_once __DIR__ . '/settings_loader.php';
    $settings = file_exists(__DIR__ . '/../config/settings.php') ? datadock_load_settings() : [];
}
if (!function_exists('sanitize_data')) {
    require_once __DIR__ . '/../includes/functions.php';
}

$siteName = get_site_name();
$version = @file_get_contents(__DIR__ . '/../VERSION');
$adminContactEmail = trim($settings['admin_contact_email'] ?? '');
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
            <?php if (!empty($adminContactEmail)): ?>
                · <a href="mailto:<?= sanitize_data($adminContactEmail) ?>">Contact Admin</a>
            <?php endif; ?>
        </p>

    </footer>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.flash.success:not(.persistent)').forEach(msg => {
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

        var nav = document.querySelector('.main-nav');
        var navToggle = document.querySelector('.nav-toggle');
        if (nav && navToggle) {
            navToggle.addEventListener('click', function () {
                var open = nav.classList.toggle('is-open');
                navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                navToggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
            });
        }

        document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                var theme = btn.getAttribute('data-theme-choice');
                var css = btn.getAttribute('data-theme-css');
                var sheet = document.getElementById('theme-stylesheet');
                if (!theme || !css || !sheet) return;
                e.preventDefault();
                document.documentElement.setAttribute('data-theme', theme);
                sheet.href = css;
                document.cookie = 'datadock_theme=' + theme + '; path=/; max-age=31536000; samesite=lax';
                document.querySelectorAll('[data-theme-choice]').forEach(function (el) {
                    el.classList.toggle('active', el.getAttribute('data-theme-choice') === theme);
                });
            });
        });

        var copiedIcon = <?= json_encode(icon_svg('check')) ?>;
        document.querySelectorAll('.btn-copy[data-copy]').forEach(function (btn) {
            if (btn.dataset.copyBound) return;
            btn.dataset.copyBound = '1';
            var orig = btn.innerHTML;
            btn.addEventListener('click', function () {
                var val = btn.getAttribute('data-copy');
                if (!val || !navigator.clipboard) return;
                navigator.clipboard.writeText(val);
                if (btn.querySelector('.icon')) {
                    btn.innerHTML = copiedIcon;
                } else {
                    btn.textContent = 'Copied';
                }
                setTimeout(function () { btn.innerHTML = orig; }, 1500);
            });
        });
    });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.file-row-expandable').forEach(function (wrap) {
            var btn = wrap.querySelector('.file-row-toggle');
            var panel = wrap.querySelector('.file-row-details');
            if (!btn || !panel) return;
            function setExpanded(on) {
                wrap.classList.toggle('is-expanded', on);
                btn.setAttribute('aria-expanded', on ? 'true' : 'false');
                panel.hidden = !on;
            }
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                setExpanded(!wrap.classList.contains('is-expanded'));
            });
        });
    });
    </script>
</body>
</html>
