<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_admin();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Admin Panel";

$settingsFile = __DIR__ . '/config/settings.php';
$siteName = get_site_name();

$section = $_GET['section'] ?? 'overview';

// Handle POST actions first
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['section']) && $_POST['section'] === 'site') {
        // --- SITE SETTINGS FORM SUBMIT ---
        if (file_exists($settingsFile)) require $settingsFile;
        $settings = $settings ?? [];
        $newName = trim($_POST['site_name'] ?? '');
        $adminContactEmail = trim($_POST['admin_contact_email'] ?? '');
        $registrationEnabled = isset($_POST['registration_enabled']);
        $enforceUniqueEmail = isset($_POST['enforce_unique_email']);
        $maxFileSize = form_size_to_bytes($_POST['max_file_size'] ?? 0, $_POST['max_file_size_unit'] ?? 'm');
        $defaultFileExpiry = trim($_POST['default_file_expiry'] ?? 'never');
        $thumbnailsEnabled = isset($_POST['thumbnails_enabled']);
        $sessionTimeoutMinutes = (int) ($_POST['session_timeout_minutes'] ?? 60);
        $installWarningEnabled = isset($_POST['install_warning_enabled']);
        $maintenanceMode = isset($_POST['maintenance_mode']);
        $debugMode = isset($_POST['debug_mode']);
        $logPath = trim($_POST['log_path'] ?? '');
        $logLevel = trim($_POST['log_level'] ?? 'warning');
        $logoUrl = trim($_POST['logo_url'] ?? '');
        $faviconUrl = trim($_POST['favicon_url'] ?? '');
        $welcomeMessage = trim($_POST['welcome_message'] ?? '');
        $theme = trim($_POST['theme'] ?? 'light');
        $fileIconsJson = trim($_POST['file_icons'] ?? '');
        $tosEnabled = isset($_POST['tos_enabled']);
        $tosText = trim($_POST['tos_text'] ?? '');

        $storageBasePath = trim($_POST['storage_base_path'] ?? '');
        $publicBrowsingEnabled = isset($_POST['public_browsing_enabled']);

        $bruteForceEnabled = isset($_POST['brute_force_enabled']);
        $maxAttempts = (int) ($_POST['max_attempts'] ?? 5);
        $lockoutMinutes = (int) ($_POST['lockout_minutes'] ?? 15);
        $lockoutWindow = (int) ($_POST['lockout_window'] ?? 10);

        $guestUploadsEnabled = isset($_POST['guest_uploads_enabled']);
        $guestMaxFiles = (int) ($_POST['guest_max_files'] ?? 0);
        $guestMaxStorage = form_size_to_bytes($_POST['guest_max_storage'] ?? 0, $_POST['guest_max_storage_unit'] ?? 'm');

        $userMaxFilesEnabled = isset($_POST['user_max_files_enabled']);
        $userMaxStorageEnabled = isset($_POST['user_max_storage_enabled']);
        $userMaxFiles = (int) ($_POST['user_max_files'] ?? 0);
        $userMaxStorage = form_size_to_bytes($_POST['user_max_storage'] ?? 0, $_POST['user_max_storage_unit'] ?? 'm');

        $serverUploadOverride = isset($_POST['server_upload_max']) && $_POST['server_upload_max'] !== '' ? form_size_to_bytes($_POST['server_upload_max'], $_POST['server_upload_max_unit'] ?? 'm') : 0;
        $serverPostOverride = isset($_POST['server_post_max']) && $_POST['server_post_max'] !== '' ? form_size_to_bytes($_POST['server_post_max'], $_POST['server_post_max_unit'] ?? 'm') : 0;

        if ($userMaxFilesEnabled && $userMaxFiles < 1) {
            $_SESSION['flash_error'][] = "❌ Max files per user must be a positive number.";
        }
        if ($userMaxStorageEnabled && $userMaxStorage < 1) {
            $_SESSION['flash_error'][] = "❌ Max storage per user must be a positive number.";
        }

        if ($newName === '') {
            $_SESSION['flash_error'][] = "❌ Site name cannot be empty.";
        } elseif ($maxFileSize <= 0) {
            $_SESSION['flash_error'][] = "❌ Max file size must be a positive number.";
        } elseif ($bruteForceEnabled && ($maxAttempts < 1 || $lockoutMinutes < 1 || $lockoutWindow < 1)) {
            $_SESSION['flash_error'][] = "❌ Brute force settings must be positive integers.";
        } elseif ($guestUploadsEnabled && ($guestMaxFiles < 1 || $guestMaxStorage < 1)) {
            $_SESSION['flash_error'][] = "❌ Guest upload settings must be positive integers.";
        } elseif (!empty($adminContactEmail) && !filter_var($adminContactEmail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'][] = "❌ Admin contact email must be a valid email address.";
        } elseif ($sessionTimeoutMinutes < 0) {
            $_SESSION['flash_error'][] = "❌ Session timeout cannot be negative.";
        }

        $validExpiryValues = ['1_minute', '30_minutes', '1_hour', '6_hours', '1_day', '1_week', '1_month', '1_year', 'never'];
        if (!in_array($defaultFileExpiry, $validExpiryValues, true)) {
            $defaultFileExpiry = 'never';
        }
        $validLogLevels = ['debug', 'info', 'warning', 'error'];
        if (!in_array($logLevel, $validLogLevels, true)) {
            $logLevel = 'warning';
        }
        $validThemes = ['light', 'dark'];
        if (!in_array($theme, $validThemes, true)) {
            $theme = 'light';
        }
        $fileIcons = [];
        if (!empty($fileIconsJson)) {
            $decoded = json_decode($fileIconsJson, true);
            if (is_array($decoded)) {
                $fileIcons = $decoded;
            }
        }

        if (empty($_SESSION['flash_error'])) {
            // When switching to relaxed email mode, drop UNIQUE on email if it exists (existing installs)
            if (!$enforceUniqueEmail) {
                try {
                    $pdo->exec("ALTER TABLE users DROP INDEX email");
                } catch (PDOException $e) {
                    // Ignore - index may not exist (new installs or already dropped)
                }
            }

            $updatedSettings = "<?php\n\$settings = [\n" .
                "    'site_name' => " . var_export($newName, true) . ",\n" .
                "    'admin_contact_email' => " . var_export($adminContactEmail, true) . ",\n" .
                "    'registration_enabled' => " . ($registrationEnabled ? 'true' : 'false') . ",\n" .
                "    'enforce_unique_email' => " . ($enforceUniqueEmail ? 'true' : 'false') . ",\n" .
                "    'max_file_size' => $maxFileSize,\n" .
                "    'default_file_expiry' => " . var_export($defaultFileExpiry, true) . ",\n" .
                "    'thumbnails_enabled' => " . ($thumbnailsEnabled ? 'true' : 'false') . ",\n" .
                "    'session_timeout_minutes' => $sessionTimeoutMinutes,\n" .
                "    'install_warning_enabled' => " . ($installWarningEnabled ? 'true' : 'false') . ",\n" .
                "    'maintenance_mode' => " . ($maintenanceMode ? 'true' : 'false') . ",\n" .
                "    'debug_mode' => " . ($debugMode ? 'true' : 'false') . ",\n" .
                "    'log_path' => " . var_export($logPath, true) . ",\n" .
                "    'log_level' => " . var_export($logLevel, true) . ",\n" .
                "    'logo_url' => " . var_export($logoUrl, true) . ",\n" .
                "    'favicon_url' => " . var_export($faviconUrl, true) . ",\n" .
                "    'welcome_message' => " . var_export($welcomeMessage, true) . ",\n" .
                "    'theme' => " . var_export($theme, true) . ",\n" .
                "    'file_icons' => " . var_export($fileIcons, true) . ",\n" .
                "    'tos_enabled' => " . ($tosEnabled ? 'true' : 'false') . ",\n" .
                "    'tos_text' => " . var_export($tosText, true) . ",\n" .
                "    'storage_base_path' => " . var_export($storageBasePath, true) . ",\n" .
                "    'public_browsing_enabled' => " . ($publicBrowsingEnabled ? 'true' : 'false') . ",\n" .
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
                $_SESSION['flash_error'][] = "❌ Failed to update site settings.";
            }

            $userIniPath = __DIR__ . '/.user.ini';
            if (!empty($_POST['server_clear_user_ini']) && file_exists($userIniPath)) {
                if (@unlink($userIniPath)) {
                    $_SESSION['flash_success'][] = "✅ Server override removed (.user.ini deleted).";
                } else {
                    $_SESSION['flash_error'][] = "❌ Could not remove .user.ini. Check permissions.";
                }
            } elseif ($serverUploadOverride > 0 || $serverPostOverride > 0) {
                $currentUploadBytes = return_bytes(ini_get('upload_max_filesize'));
                $currentPostBytes = return_bytes(ini_get('post_max_size'));
                $uploadIni = $serverUploadOverride > 0 ? bytes_to_ini_size($serverUploadOverride) : bytes_to_ini_size($currentUploadBytes);
                $postIni = $serverPostOverride > 0 ? bytes_to_ini_size($serverPostOverride) : bytes_to_ini_size($currentPostBytes);
                $userIniContent = "; DataDock server limits (optional override)\nupload_max_filesize = " . $uploadIni . "\npost_max_size = " . $postIni . "\n";
                if (@file_put_contents($userIniPath, $userIniContent)) {
                    $_SESSION['flash_success'][] = "✅ Server PHP limits written to .user.ini. They may take effect on the next request (not all hosts support .user.ini).";
                } else {
                    $_SESSION['flash_error'][] = "❌ Could not write .user.ini. Check permissions or set server limits in php.ini manually.";
                }
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
            $filePath = get_upload_path() . $file['filename'];
            $thumbPath = get_thumbnails_path() . $file['thumbnail_path'];

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
            $_SESSION['flash_warning'][] = "⚠️ No expired files found.";
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
            $clearDir(get_upload_path());
            $clearDir(get_thumbnails_path());

            write_default_settings_file();
            $_SESSION['flash_success'][] = "✅ Site has been reset to post-install state.";
        } catch (Exception $e) {
            $_SESSION['flash_error'][] = "❌ Reset failed: " . $e->getMessage();
        }

        header("Location: admin.php?section=reset");
        exit;
    }
}
clearstatcache(true, $settingsFile);
require $settingsFile;

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section admin-panel">
    <h2 class="page-title">Admin Panel</h2>

    <div class="admin-layout">
        <aside class="admin-sidebar">
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="?section=overview"<?= $section === 'overview' ? ' class="active"' : '' ?>>Overview</a></li>
                    <li><a href="?section=site"<?= $section === 'site' ? ' class="active"' : '' ?>>Site Settings</a></li>
                    <li><a href="?section=users"<?= $section === 'users' ? ' class="active"' : '' ?>>User Management</a></li>
                    <li><a href="?section=files"<?= $section === 'files' ? ' class="active"' : '' ?>>File Management</a></li>
                    <li><a href="?section=updater"<?= $section === 'updater' ? ' class="active"' : '' ?>>Updater & Changelog</a></li>
                    <li><a href="?section=reset"<?= $section === 'reset' ? ' class="active"' : '' ?>>Reset Site</a></li>
                </ul>
            </nav>
            <?php $adminEmail = trim($settings['admin_contact_email'] ?? ''); if (!empty($adminEmail)): ?>
            <div class="admin-contact" style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border-color, #ddd);font-size:0.875rem;">
                <strong>Admin Contact:</strong><br>
                <a href="mailto:<?= sanitize_data($adminEmail) ?>"><?= sanitize_data($adminEmail) ?></a>
            </div>
            <?php endif; ?>
        </aside>

        <main class="admin-content">
        <?php
        switch ($section) {
            case 'overview':
                include __DIR__ . '/admin_sections/stats_overview.php';
                break;

            case 'site':
                // Initialize site settings variables
                $adminContactEmail = trim($settings['admin_contact_email'] ?? '');
                $registrationEnabled = $settings['registration_enabled'] ?? true;
                $enforceUniqueEmail = $settings['enforce_unique_email'] ?? true;
                $maxFileSize = $settings['max_file_size'] ?? 5242880;
                $maxFileSizeDisplay = bytes_to_display($maxFileSize);
                $defaultFileExpiry = $settings['default_file_expiry'] ?? 'never';
                $thumbnailsEnabled = $settings['thumbnails_enabled'] ?? true;
                $sessionTimeoutMinutes = (int) ($settings['session_timeout_minutes'] ?? 60);
                $installWarningEnabled = $settings['install_warning_enabled'] ?? true;
                $userLimits = $settings['user_limits'] ?? [];
                $userMaxFilesEnabled = $userLimits['max_files_enabled'] ?? false;
                $userMaxFiles = $userLimits['max_files'] ?? 100;
                $userMaxStorageEnabled = $userLimits['max_storage_enabled'] ?? false;
                $userMaxStorage = $userLimits['max_storage'] ?? 104857600;
                $userMaxStorageDisplay = bytes_to_display($userMaxStorage);
                $bruteForceEnabled = $settings['brute_force']['enabled'] ?? true;
                $maxAttempts = $settings['brute_force']['max_attempts'] ?? 5;
                $lockoutMinutes = $settings['brute_force']['lockout_minutes'] ?? 15;
                $lockoutWindow = $settings['brute_force']['lockout_window'] ?? 10;
                $guestUploadsEnabled = $settings['guest_uploads']['enabled'] ?? false;
                $guestMaxFiles = $settings['guest_uploads']['max_files'] ?? 10;
                $guestMaxStorage = $settings['guest_uploads']['max_storage'] ?? 5242880;
                $guestMaxStorageDisplay = bytes_to_display($guestMaxStorage);
                $serverUploadMax = ini_get('upload_max_filesize');
                $serverPostMax = ini_get('post_max_size');
                $serverUploadMaxBytes = return_bytes($serverUploadMax);
                $serverPostMaxBytes = return_bytes($serverPostMax);
                $userIniPath = __DIR__ . '/.user.ini';
                $serverOverrideUploadDisplay = null;
                $serverOverridePostDisplay = null;
                if (file_exists($userIniPath)) {
                    $userIni = @parse_ini_file($userIniPath);
                    if (!empty($userIni['upload_max_filesize'])) {
                        $serverOverrideUploadDisplay = bytes_to_display(return_bytes($userIni['upload_max_filesize']));
                    }
                    if (!empty($userIni['post_max_size'])) {
                        $serverOverridePostDisplay = bytes_to_display(return_bytes($userIni['post_max_size']));
                    }
                }
                $maintenanceMode = $settings['maintenance_mode'] ?? false;
                $debugMode = $settings['debug_mode'] ?? false;
                $logPath = trim($settings['log_path'] ?? '');
                $logLevel = $settings['log_level'] ?? 'warning';
                $logoUrl = trim($settings['logo_url'] ?? '');
                $faviconUrl = trim($settings['favicon_url'] ?? '');
                $welcomeMessage = trim($settings['welcome_message'] ?? '');
                $theme = $settings['theme'] ?? 'light';
                $fileIcons = $settings['file_icons'] ?? [];
                $fileIconsJson = !empty($fileIcons) ? json_encode($fileIcons, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
                $tosEnabled = $settings['tos_enabled'] ?? false;
                $tosText = trim($settings['tos_text'] ?? '');

                $storageBasePath = trim($settings['storage_base_path'] ?? '');
                $publicBrowsingEnabled = !empty($settings['public_browsing_enabled']);

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
                // Fetch all file records (includes guest uploads via LEFT JOIN)
                $stmt = $pdo->query("
                    SELECT f.*, u.username 
                    FROM files f
                    LEFT JOIN users u ON f.user_id = u.id
                    ORDER BY f.upload_date DESC
                ");
                $allFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

                include __DIR__ . '/admin_sections/file_management.php';
                break;

            case 'updater':
                include __DIR__ . '/admin_sections/update_status.php';
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
            const d = document.createElement('span');
            d.className = 'datetime-date';
            d.textContent = local.toLocaleDateString();
            const t = document.createElement('span');
            t.className = 'datetime-time';
            t.textContent = local.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
            el.textContent = '';
            el.appendChild(d);
            el.appendChild(t);
        } else {
            el.textContent = '—';
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
