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

// Handle tab selection
$section = $_GET['section'] ?? 'site';


?>

<h2>Admin Panel</h2>
<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="success"><?= sanitize_data($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<div style="display: flex; gap: 20px;">
    <aside style="min-width: 200px;">
        <nav class="sidebar">
            <ul style="list-style: none; padding: 0;">
                <li><a href="?section=site"<?= $section === 'site' ? ' class="active"' : '' ?>>Site Settings</a></li>
                <li><a href="?section=users"<?= $section === 'users' ? ' class="active"' : '' ?>>User Management</a></li>
                <li><a href="?section=files"<?= $section === 'files' ? ' class="active"' : '' ?>>File Management</a></li>
            </ul>
        </nav>
    </aside>

    <main style="flex-grow: 1;">
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
            if ($section === 'site') {
                include __DIR__ . '/admin_sections/site_settings.php';
            } elseif ($section === 'users') {
                include __DIR__ . '/admin_sections/user_management.php';
            } elseif ($section === 'files') {
                include __DIR__ . '/admin_sections/file_management.php';
            } else {
                echo "<p>Unknown section.</p>";
            }
        ?>

    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const elements = document.querySelectorAll('.utc-datetime');
    elements.forEach(el => {
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