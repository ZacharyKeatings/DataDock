<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_admin();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Admin Panel";

$settingsFile = __DIR__ . '/config/settings.php';
$siteName = get_site_name();

$section = $_GET['section'] ?? 'site';
$settings = require $settingsFile;

// Handle POST actions first
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['section']) && $_POST['section'] === 'site') {
        // --- SITE SETTINGS FORM SUBMIT ---
        $newName = trim($_POST['site_name'] ?? '');
        $registrationEnabled = isset($_POST['registration_enabled']);
        $maxFileSize = (int) ($_POST['max_file_size'] ?? 0);

        $bruteForceEnabled = isset($_POST['brute_force_enabled']);
        $maxAttempts = (int) ($_POST['max_attempts'] ?? 5);
        $lockoutMinutes = (int) ($_POST['lockout_minutes'] ?? 15);
        $lockoutWindow = (int) ($_POST['lockout_window'] ?? 10);

        $guestUploadsEnabled = isset($_POST['guest_uploads_enabled']);
        $guestMaxFiles = (int) ($_POST['guest_max_files'] ?? 0);
        $guestMaxStorage = (int) ($_POST['guest_max_storage'] ?? 0);

        $userMaxFilesEnabled = isset($_POST['user_max_files_enabled']);
        $userMaxStorageEnabled = isset($_POST['user_max_storage_enabled']);
        $userMaxFiles = (int) ($_POST['user_max_files'] ?? 0);
        $userMaxStorage = (int) ($_POST['user_max_storage'] ?? 0);

        if ($userMaxFilesEnabled && $userMaxFiles < 1) {
            $_SESSION['flash_error'][] = "Max files per user must be a positive number.";
        }
        if ($userMaxStorageEnabled && $userMaxStorage < 1) {
            $_SESSION['flash_error'][] = "Max storage per user must be a positive number.";
        }

        if ($newName === '') {
            $_SESSION['flash_error'][] = "Site name cannot be empty.";
        } elseif ($maxFileSize <= 0) {
            $_SESSION['flash_error'][] = "Max file size must be a positive number.";
        } elseif ($bruteForceEnabled && ($maxAttempts < 1 || $lockoutMinutes < 1 || $lockoutWindow < 1)) {
            $_SESSION['flash_error'][] = "Brute force settings must be positive integers.";
        } elseif ($guestUploadsEnabled && ($guestMaxFiles < 1 || $guestMaxStorage < 1)) {
            $_SESSION['flash_error'][] = "Guest upload settings must be positive integers.";
        }

        if (empty($_SESSION['flash_error'])) {
            $updatedSettings = "<?php\n\$settings = [\n" .
                "    'site_name' => " . var_export($newName, true) . ",\n" .
                "    'registration_enabled' => " . ($registrationEnabled ? 'true' : 'false') . ",\n" .
                "    'max_file_size' => $maxFileSize,\n" .
                "    'brute_force' => [\n" .
                "        'enabled' => " . ($bruteForceEnabled ? 'true' : 'false') . ",\n" .
                "        'max_attempts' => $maxAttempts,\n" .
                "        'lockout_minutes' => $lockoutMinutes,\n" .
                "        'lockout_window' => $lockoutWindow\n" .
                "    ],\n" .
                "    'guest_uploads' => [\n" .
                "        'enabled' => " . ($guestUploadsEnabled ? 'true' : 'false') . ",\n" .
                "        'max_files' => $guestMaxFiles,\n" .
                "        'max_storage' => $guestMaxStorage\n" .
                "    ],\n" .
                "    'user_limits' => [\n" .
                "        'max_files_enabled' => " . ($userMaxFilesEnabled ? 'true' : 'false') . ",\n" .
                "        'max_files' => $userMaxFiles,\n" .
                "        'max_storage_enabled' => " . ($userMaxStorageEnabled ? 'true' : 'false') . ",\n" .
                "        'max_storage' => $userMaxStorage\n" .
                "    ],\n" .
                "];\n?>";

            if (file_put_contents($settingsFile, $updatedSettings)) {
                $_SESSION['flash_success'][] = "✅ Site settings updated successfully.";
            } else {
                $_SESSION['flash_error'][] = "Failed to update site settings.";
            }
        }

        header("Location: admin.php?section=site");
        exit;
    }

    if (isset($_POST['purge'])) {
        // --- PURGE EXPIRED FILES ---
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("SELECT * FROM files WHERE expiry_date IS NOT NULL AND expiry_date < ?");
        $stmt->execute([$now]);
        $expiredFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $deletedCount = 0;
        $freedBytes = 0;
        $filetypeBreakdown = [];

        foreach ($expiredFiles as $file) {
            $filePath = __DIR__ . '/uploads/' . $file['filename'];
            $thumbPath = __DIR__ . '/thumbnails/' . $file['thumbnail_path'];

            if (file_exists($filePath) && unlink($filePath)) {
                $freedBytes += $file['filesize'];
                $type = $file['filetype'] ?? 'unknown';
                $filetypeBreakdown[$type] = ($filetypeBreakdown[$type] ?? 0) + 1;
            }

            if (!empty($file['thumbnail_path']) && file_exists($thumbPath)) {
                unlink($thumbPath);
            }

            $pdo->prepare("DELETE FROM files WHERE id = ?")->execute([$file['id']]);
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            $_SESSION['flash_success'][] = "✅ Purged $deletedCount expired file(s).";
            $_SESSION['flash_success'][] = "💾 Total space freed: " . format_filesize($freedBytes) . ".";
            if (!empty($filetypeBreakdown)) {
                $summary = "📊 Filetype breakdown:";
                foreach ($filetypeBreakdown as $type => $count) {
                    $summary .= " $type ($count),";
                }
                $_SESSION['flash_success'][] = rtrim($summary, ',');
            }
        } else {
            $_SESSION['flash_warning'][] = "No expired files found.";
        }

        header("Location: admin.php?section=files");
        exit;
    }

    if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'yes') {
        // --- RESET SITE ---
        try {
            $pdo->prepare("DELETE FROM users WHERE role != 'admin'")->execute();
            $pdo->exec("DELETE FROM files");
            $pdo->exec("DELETE FROM login_attempts");

            $clearDir = function ($path) {
                foreach (glob($path . '*') as $file) {
                    if (is_file($file)) unlink($file);
                }
            };
            $clearDir(__DIR__ . '/uploads/');
            $clearDir(__DIR__ . '/thumbnails/');

            write_default_settings_file();
            $_SESSION['flash_success'][] = "✅ Site has been reset to post-install state.";
        } catch (Exception $e) {
            $_SESSION['flash_error'][] = "Reset failed: " . $e->getMessage();
        }

        header("Location: admin.php?section=reset");
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section admin-panel">
    <h2 class="page-title">Admin Panel</h2>

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
        <?php
        switch ($section) {
            case 'site':
                // Initialize site settings variables
                $registrationEnabled = $settings['registration_enabled'] ?? true;
                $maxFileSize = $settings['max_file_size'] ?? 5242880;
                $userLimits = $settings['user_limits'] ?? [];
                $userMaxFilesEnabled = $userLimits['max_files_enabled'] ?? false;
                $userMaxFiles = $userLimits['max_files'] ?? 100;
                $userMaxStorageEnabled = $userLimits['max_storage_enabled'] ?? false;
                $userMaxStorage = $userLimits['max_storage'] ?? 104857600;
                $bruteForceEnabled = $settings['brute_force']['enabled'] ?? true;
                $maxAttempts = $settings['brute_force']['max_attempts'] ?? 5;
                $lockoutMinutes = $settings['brute_force']['lockout_minutes'] ?? 15;
                $lockoutWindow = $settings['brute_force']['lockout_window'] ?? 10;
                $guestUploadsEnabled = $settings['guest_uploads']['enabled'] ?? false;
                $guestMaxFiles = $settings['guest_uploads']['max_files'] ?? 10;
                $guestMaxStorage = $settings['guest_uploads']['max_storage'] ?? 5242880;

                include __DIR__ . '/admin_sections/site_settings.php';
                break;

            case 'users':
                // Fetch user stats
                $stmt = $pdo->query("
                    SELECT u.id, u.username, u.email, u.role, u.created_at, 
                        COUNT(f.id) AS file_count, 
                        COALESCE(SUM(f.filesize), 0) AS total_size
                    FROM users u
                    LEFT JOIN files f ON u.id = f.user_id
                    GROUP BY u.id
                    ORDER BY u.created_at DESC
                ");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                include __DIR__ . '/admin_sections/user_management.php';
                break;

            case 'files':
                // Fetch all file records
                $stmt = $pdo->query("
                    SELECT f.*, u.username 
                    FROM files f
                    JOIN users u ON f.user_id = u.id
                    ORDER BY f.upload_date DESC
                ");
                $allFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
