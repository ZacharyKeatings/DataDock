<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_admin();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Admin Panel";
require_once __DIR__ . '/includes/header.php';

$settingsFile = __DIR__ . '/config/settings.php';
$siteName = get_site_name();
$success = '';
$errors = [];

$section = $_GET['section'] ?? 'site';
?>

<div class="page-section admin-panel">
    <h2 class="page-title">Admin Panel</h2>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="success"><?= sanitize_data($_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <div class="admin-layout">
        <aside class="admin-sidebar">
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="?section=site"<?= $section === 'site' ? ' class="active"' : '' ?>>Site Settings</a></li>
                    <li><a href="?section=users"<?= $section === 'users' ? ' class="active"' : '' ?>>User Management</a></li>
                    <li><a href="?section=files"<?= $section === 'files' ? ' class="active"' : '' ?>>File Management</a></li>
                    <li><a href="?section=reset"<?= $section === 'reset' ? ' class="active"' : '' ?>>Reset Site</a></li>
                </ul>
            </nav>
        </aside>

        <main class="admin-content">
            <?php if ($success): ?>
                <div class="success"><?= sanitize_data($success) ?></div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <div>• <?= sanitize_data($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php 
                switch ($section) {
                    case 'site':
                        include __DIR__ . '/admin_sections/site_settings.php';
                        break;
                    case 'users':
                        include __DIR__ . '/admin_sections/user_management.php';
                        break;
                    case 'files':
                        include __DIR__ . '/admin_sections/file_management.php';
                        break;
                    case 'reset':
                        include __DIR__ . '/admin_sections/reset_site.php';
                        break;
                    default:
                        echo "<p>Unknown section.</p>";
                }
            ?>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.utc-datetime').forEach(el => {
        const utc = el.dataset.utc;
        if (utc) {
            const local = new Date(utc + ' UTC');
            el.textContent = local.toLocaleString();
        } else {
            el.textContent = '—';
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
