<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_admin();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/purge_ops.php';

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
        $inviteOnlyRegistration = isset($_POST['invite_only_registration']);
        $enforceUniqueEmail = isset($_POST['enforce_unique_email']);
        $maxFileSize = form_size_to_bytes($_POST['max_file_size'] ?? 0, $_POST['max_file_size_unit'] ?? 'm');
        $defaultFileExpiry = trim($_POST['default_file_expiry'] ?? 'never');
        $trashRetentionDays = (int) ($_POST['trash_retention_days'] ?? 30);
        if ($trashRetentionDays < 0) {
            $trashRetentionDays = 0;
        }
        if ($trashRetentionDays > 3650) {
            $trashRetentionDays = 3650;
        }
        $thumbnailsEnabled = isset($_POST['thumbnails_enabled']);
        $sessionTimeoutMinutes = (int) ($_POST['session_timeout_minutes'] ?? 60);
        $securityHeadersMode = strtolower(trim((string) ($_POST['security_headers_mode'] ?? 'off')));
        if (!in_array($securityHeadersMode, ['off', 'recommended', 'strict'], true)) {
            $securityHeadersMode = 'off';
        }
        $rememberDeviceEnabled = isset($_POST['remember_device_enabled']);
        $rememberDeviceCookieDays = (int) ($_POST['remember_device_cookie_days'] ?? 30);
        if ($rememberDeviceCookieDays < 1) {
            $rememberDeviceCookieDays = 1;
        }
        if ($rememberDeviceCookieDays > 365) {
            $rememberDeviceCookieDays = 365;
        }
        $installWarningEnabled = isset($_POST['install_warning_enabled']);
        $maintenanceMode = isset($_POST['maintenance_mode']);
        $readOnlyMode = isset($_POST['read_only_mode']);
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
        $adaptiveCooldownEnabled = isset($_POST['adaptive_cooldown_enabled']);
        $adaptiveCooldownWindow = (int) ($_POST['adaptive_cooldown_ip_window_minutes'] ?? 60);
        $rateLimitUploadsEnabled = isset($_POST['rate_limit_uploads_enabled']);
        $rateLimitWindowMinutes = (int) ($_POST['rate_limit_window_minutes'] ?? 1);
        $rateLimitMaxPerIp = (int) ($_POST['rate_limit_max_per_ip'] ?? 30);
        $rateLimitMaxPerUser = (int) ($_POST['rate_limit_max_per_user'] ?? 60);
        $rewriteFileExtension = isset($_POST['rewrite_file_extension']);
        $uploadQuarantineEnabled = isset($_POST['upload_quarantine_enabled']);
        $deduplicateStorage = !empty($_POST['deduplicate_storage']);
        $foldersEnabledSetting = !empty($_POST['folders_enabled']);
        $tagsEnabledSetting = !empty($_POST['tags_enabled']);
        $hotlinkLoggingEnabled = isset($_POST['hotlink_logging_enabled']);
        $hotlinkTrustedHosts = trim((string) ($_POST['hotlink_trusted_hosts'] ?? ''));

        $opsStoragePartitionPercentEnabled = isset($_POST['ops_storage_partition_percent_enabled']);
        $opsStoragePartitionPercentThreshold = (int) ($_POST['ops_storage_partition_percent_threshold'] ?? 85);
        if ($opsStoragePartitionPercentThreshold < 1) {
            $opsStoragePartitionPercentThreshold = 1;
        }
        if ($opsStoragePartitionPercentThreshold > 100) {
            $opsStoragePartitionPercentThreshold = 100;
        }
        $opsUserQuotaPercentEnabled = isset($_POST['ops_user_quota_percent_enabled']);
        $opsUserQuotaPercentThreshold = (int) ($_POST['ops_user_quota_percent_threshold'] ?? 90);
        if ($opsUserQuotaPercentThreshold < 1) {
            $opsUserQuotaPercentThreshold = 1;
        }
        if ($opsUserQuotaPercentThreshold > 100) {
            $opsUserQuotaPercentThreshold = 100;
        }

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
                "    'invite_only_registration' => " . ($inviteOnlyRegistration ? 'true' : 'false') . ",\n" .
                "    'enforce_unique_email' => " . ($enforceUniqueEmail ? 'true' : 'false') . ",\n" .
                "    'max_file_size' => $maxFileSize,\n" .
                "    'default_file_expiry' => " . var_export($defaultFileExpiry, true) . ",\n" .
                "    'thumbnails_enabled' => " . ($thumbnailsEnabled ? 'true' : 'false') . ",\n" .
                "    'session_timeout_minutes' => $sessionTimeoutMinutes,\n" .
                "    'security_headers_mode' => " . var_export($securityHeadersMode, true) . ",\n" .
                "    'remember_device' => [\n" .
                "        'enabled' => " . ($rememberDeviceEnabled ? 'true' : 'false') . ",\n" .
                "        'cookie_days' => $rememberDeviceCookieDays\n" .
                "    ],\n" .
                "    'install_warning_enabled' => " . ($installWarningEnabled ? 'true' : 'false') . ",\n" .
                "    'maintenance_mode' => " . ($maintenanceMode ? 'true' : 'false') . ",\n" .
                "    'read_only_mode' => " . ($readOnlyMode ? 'true' : 'false') . ",\n" .
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
                "        'lockout_window' => $lockoutWindow,\n" .
                "        'adaptive_cooldown_enabled' => " . ($adaptiveCooldownEnabled ? 'true' : 'false') . ",\n" .
                "        'adaptive_cooldown_ip_window_minutes' => $adaptiveCooldownWindow,\n" .
                "        'adaptive_cooldown_steps' => [5 => 5, 15 => 15, 30 => 60]\n" .
                "    ],\n" .
                "    'rate_limit_uploads' => [\n" .
                "        'enabled' => " . ($rateLimitUploadsEnabled ? 'true' : 'false') . ",\n" .
                "        'window_minutes' => $rateLimitWindowMinutes,\n" .
                "        'max_per_ip' => $rateLimitMaxPerIp,\n" .
                "        'max_per_user' => $rateLimitMaxPerUser\n" .
                "    ],\n" .
                "    'rewrite_file_extension' => " . ($rewriteFileExtension ? 'true' : 'false') . ",\n" .
                "    'upload_quarantine_enabled' => " . ($uploadQuarantineEnabled ? 'true' : 'false') . ",\n" .
                "    'deduplicate_storage' => " . ($deduplicateStorage ? 'true' : 'false') . ",\n" .
                "    'folders_enabled' => " . ($foldersEnabledSetting ? 'true' : 'false') . ",\n" .
                "    'tags_enabled' => " . ($tagsEnabledSetting ? 'true' : 'false') . ",\n" .
                "    'hotlink_logging_enabled' => " . ($hotlinkLoggingEnabled ? 'true' : 'false') . ",\n" .
                "    'hotlink_trusted_hosts' => " . var_export($hotlinkTrustedHosts, true) . ",\n" .
                "    'ops_alerts' => [\n" .
                "        'storage_partition_percent_enabled' => " . ($opsStoragePartitionPercentEnabled ? 'true' : 'false') . ",\n" .
                "        'storage_partition_percent_threshold' => $opsStoragePartitionPercentThreshold,\n" .
                "        'user_quota_percent_enabled' => " . ($opsUserQuotaPercentEnabled ? 'true' : 'false') . ",\n" .
                "        'user_quota_percent_threshold' => $opsUserQuotaPercentThreshold\n" .
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
                "    'trash_retention_days' => $trashRetentionDays,\n" .
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

    if (isset($_POST['add_storage_partition'])) {
        $pname = trim((string) ($_POST['partition_name'] ?? ''));
        $proot = trim((string) ($_POST['partition_root'] ?? ''));
        if ($pname === '') {
            $_SESSION['flash_error'][] = '❌ Partition name is required.';
        } else {
            try {
                $pdo->prepare('INSERT INTO storage_partitions (name, root_path, is_default, sort_order) VALUES (?, ?, 0, 0)')->execute([$pname, $proot]);
                $_SESSION['flash_success'][] = '✅ Storage partition added. Create uploads/ and thumbnails/ under the root if needed.';
            } catch (PDOException $e) {
                $_SESSION['flash_error'][] = '❌ Could not add partition.';
            }
        }
        header('Location: admin.php?section=storage');
        exit;
    }

    if (isset($_POST['set_default_partition'])) {
        $pid = (int) ($_POST['partition_id'] ?? 0);
        if ($pid > 0) {
            $pdo->exec('UPDATE storage_partitions SET is_default = 0');
            $pdo->prepare('UPDATE storage_partitions SET is_default = 1 WHERE id = ?')->execute([$pid]);
            $_SESSION['flash_success'][] = '✅ Default storage partition updated.';
        }
        header('Location: admin.php?section=storage');
        exit;
    }

    if (isset($_POST['purge'])) {
        $result = datadock_purge_expired_files($pdo);
        $deletedCount = $result['deleted'];
        $freedBytes = $result['freed_bytes'];
        $filetypeBreakdown = $result['filetype_breakdown'];
        $adminUid = (int) ($_SESSION['user_id'] ?? 0);
        datadock_log_activity($pdo, 'admin_purge_expired', [
            'actor_user_id' => $adminUid > 0 ? $adminUid : null,
            'detail' => ['deleted' => $deletedCount, 'freed_bytes' => $freedBytes],
        ]);

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

    if (isset($_POST['purge_trash'])) {
        if (file_exists($settingsFile)) {
            require $settingsFile;
        }
        $settings = $settings ?? [];
        $retentionDays = (int) ($settings['trash_retention_days'] ?? 30);

        $result = datadock_purge_trash_by_retention($pdo, $retentionDays);
        $deletedCount = $result['deleted'];
        $freedBytes = $result['freed_bytes'];
        $adminUid = (int) ($_SESSION['user_id'] ?? 0);
        datadock_log_activity($pdo, 'admin_purge_trash', [
            'actor_user_id' => $adminUid > 0 ? $adminUid : null,
            'detail' => ['deleted' => $deletedCount, 'freed_bytes' => $freedBytes, 'retention_days' => $retentionDays],
        ]);

        if ($deletedCount > 0) {
            $_SESSION['flash_success'][] = "✅ Purged $deletedCount file(s) from trash.";
            $_SESSION['flash_success'][] = "💾 Space freed: " . format_filesize($freedBytes) . ".";
        } else {
            $_SESSION['flash_warning'][] = "⚠️ No files to purge (trash empty or none past retention).";
        }
        header("Location: admin.php?section=files");
        exit;
    }

    if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'yes') {
        // --- RESET SITE ---
        try {
            $pdo->prepare('DELETE FROM users WHERE role != \'admin\'')->execute();
            $pdo->exec('DELETE FROM files');
            $pdo->exec('DELETE FROM storage_objects');
            $pdo->exec('DELETE FROM folders');
            $pdo->exec('DELETE FROM tags');
            $pdo->exec('DELETE FROM login_attempts');
            $pdo->exec('DELETE FROM upload_rate_log');
            $pdo->exec('DELETE FROM hotlink_log');
            try {
                $pdo->exec('DELETE FROM activity_log');
            } catch (PDOException $e) {
            }
            try {
                $pdo->exec('DELETE FROM file_reports');
            } catch (PDOException $e) {
            }
            try {
                $pdo->exec('DELETE FROM user_storage_snapshots');
            } catch (PDOException $e) {
            }
            try {
                $pdo->exec('DELETE FROM app_secrets');
            } catch (PDOException $e) {
            }
            try {
                $pdo->exec('DELETE FROM share_folders');
            } catch (PDOException $e) {
            }

            datadock_clear_all_partition_files_on_disk($pdo);

            write_default_settings_file();
            $_SESSION['flash_success'][] = "✅ Site has been reset to post-install state.";
        } catch (Exception $e) {
            $_SESSION['flash_error'][] = "❌ Reset failed: " . $e->getMessage();
        }

        header("Location: admin.php?section=reset");
        exit;
    }

    if (isset($_POST['hotlink_purge_all'])) {
        try {
            $pdo->exec('DELETE FROM hotlink_log');
            $_SESSION['flash_success'][] = '✅ Hotlink log cleared.';
        } catch (PDOException $e) {
            $_SESSION['flash_error'][] = '❌ Could not clear hotlink log.';
        }
        header('Location: admin.php?section=hotlinks');
        exit;
    }

    if (isset($_POST['hotlink_purge_old'])) {
        $days = (int) ($_POST['purge_days'] ?? 90);
        if ($days < 1) {
            $days = 1;
        }
        if ($days > 3650) {
            $days = 3650;
        }
        try {
            $stmt = $pdo->prepare('DELETE FROM hotlink_log WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)');
            $stmt->execute([$days]);
            $removed = $stmt->rowCount();
            $_SESSION['flash_success'][] = "✅ Removed {$removed} hotlink log entr" . ($removed === 1 ? 'y' : 'ies') . " older than {$days} day(s).";
        } catch (PDOException $e) {
            $_SESSION['flash_error'][] = '❌ Could not purge hotlink log.';
        }
        header('Location: admin.php?section=hotlinks');
        exit;
    }

    if (isset($_POST['activity_log_purge_days'])) {
        $days = (int) ($_POST['activity_log_purge_days'] ?? 90);
        if ($days < 1) {
            $days = 1;
        }
        if ($days > 3650) {
            $days = 3650;
        }
        try {
            $stmt = $pdo->prepare('DELETE FROM activity_log WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)');
            $stmt->execute([$days]);
            $removed = $stmt->rowCount();
            $_SESSION['flash_success'][] = "✅ Removed {$removed} activity log entr" . ($removed === 1 ? 'y' : 'ies') . " older than {$days} day(s).";
        } catch (PDOException $e) {
            $_SESSION['flash_error'][] = '❌ Could not purge activity log.';
        }
        header('Location: admin.php?section=audit');
        exit;
    }

    if (isset($_POST['moderation_report_action'])) {
        $reportId = (int) ($_POST['report_id'] ?? 0);
        $action = trim((string) ($_POST['moderation_report_action'] ?? ''));
        $reviewNote = trim((string) ($_POST['review_note'] ?? ''));
        if (strlen($reviewNote) > 1000) {
            $reviewNote = substr($reviewNote, 0, 1000);
        }
        $adminUid = (int) ($_SESSION['user_id'] ?? 0);

        if ($reportId <= 0 || !in_array($action, ['dismiss', 'quarantine', 'delete'], true)) {
            $_SESSION['flash_error'][] = '❌ Invalid moderation action.';
            header('Location: admin.php?section=reports');
            exit;
        }

        $st = $pdo->prepare("
            SELECT r.*, f.deleted_at, f.quarantine_status, f.original_name, f.filename
            FROM file_reports r
            LEFT JOIN files f ON r.file_id = f.id
            WHERE r.id = ?
            LIMIT 1
        ");
        $st->execute([$reportId]);
        $report = $st->fetch(PDO::FETCH_ASSOC);
        if (!$report) {
            $_SESSION['flash_error'][] = '❌ Report not found.';
            header('Location: admin.php?section=reports');
            exit;
        }
        if (($report['status'] ?? 'open') !== 'open') {
            $_SESSION['flash_warning'][] = '⚠️ This report is already closed.';
            header('Location: admin.php?section=reports');
            exit;
        }

        $fileId = (int) ($report['file_id'] ?? 0);
        $fileName = (string) ($report['original_name'] ?? $report['filename'] ?? ('#' . $fileId));
        $actionTaken = null;

        if ($action === 'dismiss') {
            $actionTaken = 'dismissed';
            $up = $pdo->prepare("
                UPDATE file_reports
                SET status = 'dismissed',
                    reviewed_by_user_id = ?,
                    reviewed_at = UTC_TIMESTAMP(),
                    action_taken = ?,
                    review_note = ?
                WHERE id = ? AND status = 'open'
            ");
            $up->execute([$adminUid > 0 ? $adminUid : null, $actionTaken, $reviewNote !== '' ? $reviewNote : null, $reportId]);
            datadock_log_activity($pdo, 'admin_report_dismiss', [
                'actor_user_id' => $adminUid > 0 ? $adminUid : null,
                'file_id' => $fileId > 0 ? $fileId : null,
                'related_user_id' => (int) ($report['reporter_user_id'] ?? 0),
                'detail' => ['report_id' => $reportId, 'name' => $fileName],
            ]);
            $_SESSION['flash_success'][] = '✅ Report dismissed.';
            header('Location: admin.php?section=reports');
            exit;
        }

        if ($action === 'quarantine' && $fileId > 0) {
            $pdo->prepare("UPDATE files SET quarantine_status = 'pending' WHERE id = ? AND deleted_at IS NULL")->execute([$fileId]);
            $actionTaken = 'quarantine';
        } elseif ($action === 'delete' && $fileId > 0) {
            $pdo->prepare('UPDATE files SET deleted_at = UTC_TIMESTAMP() WHERE id = ? AND deleted_at IS NULL')->execute([$fileId]);
            $actionTaken = 'delete_to_trash';
        }

        $up = $pdo->prepare("
            UPDATE file_reports
            SET status = 'actioned',
                reviewed_by_user_id = ?,
                reviewed_at = UTC_TIMESTAMP(),
                action_taken = ?,
                review_note = ?
            WHERE id = ? AND status = 'open'
        ");
        $up->execute([$adminUid > 0 ? $adminUid : null, $actionTaken, $reviewNote !== '' ? $reviewNote : null, $reportId]);
        datadock_log_activity($pdo, 'admin_report_action', [
            'actor_user_id' => $adminUid > 0 ? $adminUid : null,
            'file_id' => $fileId > 0 ? $fileId : null,
            'related_user_id' => (int) ($report['reporter_user_id'] ?? 0),
            'detail' => ['report_id' => $reportId, 'action' => $actionTaken, 'name' => $fileName],
        ]);
        $_SESSION['flash_success'][] = '✅ Report action applied.';
        header('Location: admin.php?section=reports');
        exit;
    }

    if (isset($_POST['ops_run_disk_scan'])) {
        require_once __DIR__ . '/includes/integrity.php';
        $_SESSION['ops_disk_scan_result'] = datadock_scan_uploads_vs_database($pdo);
        header('Location: admin.php?section=ops');
        exit;
    }

    if (isset($_POST['ops_verify_checksums'])) {
        require_once __DIR__ . '/includes/integrity.php';
        $limit = (int) ($_POST['verify_limit'] ?? 0);
        if ($limit < 0) {
            $limit = 0;
        }
        if ($limit > 50000) {
            $limit = 50000;
        }
        $_SESSION['ops_verify_result'] = datadock_verify_file_checksums($pdo, $limit);
        header('Location: admin.php?section=ops');
        exit;
    }

    if (isset($_POST['ops_rehash_from_disk'])) {
        require_once __DIR__ . '/includes/integrity.php';
        $limit = (int) ($_POST['rehash_limit'] ?? 0);
        if ($limit < 0) {
            $limit = 0;
        }
        if ($limit > 50000) {
            $limit = 50000;
        }
        $_SESSION['ops_rehash_result'] = $rr = datadock_rehash_files_from_disk($pdo, $limit);
        $adminUid = (int) ($_SESSION['user_id'] ?? 0);
        datadock_log_activity($pdo, 'admin_rehash_checksums', [
            'actor_user_id' => $adminUid > 0 ? $adminUid : null,
            'detail' => [
                'updated' => $rr['updated'] ?? 0,
                'skipped' => $rr['skipped'] ?? 0,
                'error_count' => count($rr['errors'] ?? []),
            ],
        ]);
        header('Location: admin.php?section=ops');
        exit;
    }
}
clearstatcache(true, $settingsFile);
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();

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
                    <li><a href="?section=storage"<?= $section === 'storage' ? ' class="active"' : '' ?>>Storage partitions</a></li>
                    <li><a href="?section=users"<?= $section === 'users' ? ' class="active"' : '' ?>>User Management</a></li>
                    <li><a href="?section=files"<?= $section === 'files' ? ' class="active"' : '' ?>>File Management</a></li>
                    <li><a href="?section=hotlinks"<?= $section === 'hotlinks' ? ' class="active"' : '' ?>>Hotlink log</a></li>
                    <li><a href="?section=audit"<?= $section === 'audit' ? ' class="active"' : '' ?>>Activity log</a></li>
                    <li><a href="?section=reports"<?= $section === 'reports' ? ' class="active"' : '' ?>>Reports &amp; moderation</a></li>
                    <li><a href="?section=ops"<?= $section === 'ops' ? ' class="active"' : '' ?>>Backup &amp; integrity</a></li>
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
                // Form reflects values stored in config/settings.php (not env-only overrides)
                $fileSettings = datadock_load_settings_from_file();
                $adminContactEmail = trim($fileSettings['admin_contact_email'] ?? '');
                $registrationEnabled = $fileSettings['registration_enabled'] ?? true;
                $inviteOnlyRegistration = !empty($fileSettings['invite_only_registration']);
                $enforceUniqueEmail = $fileSettings['enforce_unique_email'] ?? true;
                $maxFileSize = $fileSettings['max_file_size'] ?? 5242880;
                $maxFileSizeDisplay = bytes_to_display($maxFileSize);
                $defaultFileExpiry = $fileSettings['default_file_expiry'] ?? 'never';
                $trashRetentionDays = (int) ($fileSettings['trash_retention_days'] ?? 30);
                $thumbnailsEnabled = $fileSettings['thumbnails_enabled'] ?? true;
                $sessionTimeoutMinutes = (int) ($fileSettings['session_timeout_minutes'] ?? 60);
                $securityHeadersMode = strtolower(trim((string) ($fileSettings['security_headers_mode'] ?? 'off')));
                if (!in_array($securityHeadersMode, ['off', 'recommended', 'strict'], true)) {
                    $securityHeadersMode = 'off';
                }
                $rememberDeviceCfg = is_array($fileSettings['remember_device'] ?? null) ? $fileSettings['remember_device'] : [];
                $rememberDeviceEnabled = !array_key_exists('enabled', $rememberDeviceCfg) || !empty($rememberDeviceCfg['enabled']);
                $rememberDeviceCookieDays = (int) ($rememberDeviceCfg['cookie_days'] ?? 30);
                if ($rememberDeviceCookieDays < 1) {
                    $rememberDeviceCookieDays = 1;
                }
                if ($rememberDeviceCookieDays > 365) {
                    $rememberDeviceCookieDays = 365;
                }
                $installWarningEnabled = $fileSettings['install_warning_enabled'] ?? true;
                $userLimits = $fileSettings['user_limits'] ?? [];
                $userMaxFilesEnabled = $userLimits['max_files_enabled'] ?? false;
                $userMaxFiles = $userLimits['max_files'] ?? 100;
                $userMaxStorageEnabled = $userLimits['max_storage_enabled'] ?? false;
                $userMaxStorage = $userLimits['max_storage'] ?? 104857600;
                $userMaxStorageDisplay = bytes_to_display($userMaxStorage);
                $bruteForceEnabled = $fileSettings['brute_force']['enabled'] ?? true;
                $maxAttempts = $fileSettings['brute_force']['max_attempts'] ?? 5;
                $lockoutMinutes = $fileSettings['brute_force']['lockout_minutes'] ?? 15;
                $lockoutWindow = $fileSettings['brute_force']['lockout_window'] ?? 10;
                $adaptiveCooldownEnabled = !empty($fileSettings['brute_force']['adaptive_cooldown_enabled']);
                $adaptiveCooldownWindow = (int) ($fileSettings['brute_force']['adaptive_cooldown_ip_window_minutes'] ?? 60);
                $rateLimitUploadsEnabled = !empty($fileSettings['rate_limit_uploads']['enabled']);
                $rateLimitWindowMinutes = (int) ($fileSettings['rate_limit_uploads']['window_minutes'] ?? 1);
                $rateLimitMaxPerIp = (int) ($fileSettings['rate_limit_uploads']['max_per_ip'] ?? 30);
                $rateLimitMaxPerUser = (int) ($fileSettings['rate_limit_uploads']['max_per_user'] ?? 60);
                $rewriteFileExtension = !empty($fileSettings['rewrite_file_extension']);
                $uploadQuarantineEnabled = !empty($fileSettings['upload_quarantine_enabled']);
                $guestUploadsEnabled = $fileSettings['guest_uploads']['enabled'] ?? false;
                $guestMaxFiles = $fileSettings['guest_uploads']['max_files'] ?? 10;
                $guestMaxStorage = $fileSettings['guest_uploads']['max_storage'] ?? 5242880;
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
                $maintenanceMode = $fileSettings['maintenance_mode'] ?? false;
                $readOnlyMode = !empty($fileSettings['read_only_mode']);
                $debugMode = $fileSettings['debug_mode'] ?? false;
                $logPath = trim($fileSettings['log_path'] ?? '');
                $logLevel = $fileSettings['log_level'] ?? 'warning';
                $logoUrl = trim($fileSettings['logo_url'] ?? '');
                $faviconUrl = trim($fileSettings['favicon_url'] ?? '');
                $welcomeMessage = trim($fileSettings['welcome_message'] ?? '');
                $theme = $fileSettings['theme'] ?? 'light';
                $fileIcons = $fileSettings['file_icons'] ?? [];
                $fileIconsJson = !empty($fileIcons) ? json_encode($fileIcons, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
                $tosEnabled = $fileSettings['tos_enabled'] ?? false;
                $tosText = trim($fileSettings['tos_text'] ?? '');

                $storageBasePath = trim($fileSettings['storage_base_path'] ?? '');
                $publicBrowsingEnabled = !empty($fileSettings['public_browsing_enabled']);
                $deduplicateStorage = !empty($fileSettings['deduplicate_storage'] ?? true);
                $foldersEnabledSetting = !isset($fileSettings['folders_enabled']) || !empty($fileSettings['folders_enabled']);
                $tagsEnabledSetting = !isset($fileSettings['tags_enabled']) || !empty($fileSettings['tags_enabled']);
                $hotlinkLoggingEnabled = !isset($fileSettings['hotlink_logging_enabled']) || !empty($fileSettings['hotlink_logging_enabled']);
                $hotlinkTrustedHosts = trim($fileSettings['hotlink_trusted_hosts'] ?? '');

                $opsAl = $fileSettings['ops_alerts'] ?? [];
                $opsStoragePartitionPercentEnabled = !empty($opsAl['storage_partition_percent_enabled']);
                $opsStoragePartitionPercentThreshold = (int) ($opsAl['storage_partition_percent_threshold'] ?? 85);
                $opsUserQuotaPercentEnabled = !empty($opsAl['user_quota_percent_enabled']);
                $opsUserQuotaPercentThreshold = (int) ($opsAl['user_quota_percent_threshold'] ?? 90);

                include __DIR__ . '/admin_sections/site_settings.php';
                break;

            case 'users':
                // Fetch user stats
                $stmt = $pdo->query("
                    SELECT u.id, u.username, u.email, u.role, u.created_at, u.storage_partition_id,
                        COUNT(f.id) AS file_count,
                        COALESCE(SUM(f.filesize), 0) AS total_size
                    FROM users u
                    LEFT JOIN files f ON u.id = f.user_id AND f.deleted_at IS NULL
                    GROUP BY u.id, u.username, u.email, u.role, u.created_at, u.storage_partition_id
                    ORDER BY u.created_at DESC
                ");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $storagePartitionsList = $pdo->query('SELECT id, name, is_default FROM storage_partitions ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);

                include __DIR__ . '/admin_sections/user_management.php';
                break;

            case 'storage':
                $stmt = $pdo->query("
                    SELECT sp.*,
                        (SELECT COUNT(*) FROM files f WHERE f.storage_partition_id = sp.id AND f.deleted_at IS NULL) AS file_count,
                        (SELECT COUNT(*) FROM users u WHERE u.storage_partition_id = sp.id) AS user_count
                    FROM storage_partitions sp
                    ORDER BY sp.sort_order, sp.id
                ");
                $storagePartitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                include __DIR__ . '/admin_sections/storage_partitions.php';
                break;

            case 'audit':
                $perPage = 50;
                $page = isset($_GET['p']) && is_numeric($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                $offset = ($page - 1) * $perPage;
                $activityTotal = 0;
                $activityRows = [];
                $activityError = null;
                try {
                    $activityTotal = (int) $pdo->query('SELECT COUNT(*) FROM activity_log')->fetchColumn();
                    $lim = (int) $perPage;
                    $off = (int) $offset;
                    $activityRows = $pdo->query(
                        "SELECT a.*, u.username AS actor_username
                        FROM activity_log a
                        LEFT JOIN users u ON a.actor_user_id = u.id
                        ORDER BY a.id DESC LIMIT {$lim} OFFSET {$off}"
                    )->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $activityError = $e->getMessage();
                }
                $activityTotalPages = $activityTotal > 0 ? (int) ceil($activityTotal / $perPage) : 1;
                include __DIR__ . '/admin_sections/activity_log.php';
                break;

            case 'reports':
                $perPage = 50;
                $page = isset($_GET['p']) && is_numeric($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                $offset = ($page - 1) * $perPage;
                $reportStatus = isset($_GET['status']) && in_array($_GET['status'], ['open', 'dismissed', 'actioned'], true) ? $_GET['status'] : 'open';
                $reportIdFilter = isset($_GET['report_id']) && is_numeric($_GET['report_id']) ? max(0, (int) $_GET['report_id']) : 0;
                $reportsFileIdFilter = isset($_GET['file_id']) && is_numeric($_GET['file_id']) ? max(0, (int) $_GET['file_id']) : 0;
                $reportsTotal = 0;
                $reportsRows = [];
                $reportsError = null;
                try {
                    $lim = (int) $perPage;
                    $off = (int) $offset;
                    $reportSelect = "
                        SELECT r.*,
                                ru.username AS reporter_username,
                                au.username AS reviewer_username,
                                f.original_name,
                                f.filename,
                                f.deleted_at,
                                f.quarantine_status
                         FROM file_reports r
                         LEFT JOIN users ru ON r.reporter_user_id = ru.id
                         LEFT JOIN users au ON r.reviewed_by_user_id = au.id
                         LEFT JOIN files f ON r.file_id = f.id
                    ";

                    if ($reportIdFilter > 0) {
                        $stTotal = $pdo->prepare('SELECT COUNT(*) FROM file_reports WHERE id = ?');
                        $stTotal->execute([$reportIdFilter]);
                        $reportsTotal = (int) $stTotal->fetchColumn();
                        $stRows = $pdo->prepare($reportSelect . ' WHERE r.id = ? ORDER BY r.id DESC LIMIT 1');
                        $stRows->execute([$reportIdFilter]);
                        $reportsRows = $stRows->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $where = 'r.status = ?';
                        $params = [$reportStatus];
                        if ($reportsFileIdFilter > 0) {
                            $where .= ' AND r.file_id = ?';
                            $params[] = $reportsFileIdFilter;
                        }
                        $stTotal = $pdo->prepare("SELECT COUNT(*) FROM file_reports r WHERE {$where}");
                        $stTotal->execute($params);
                        $reportsTotal = (int) $stTotal->fetchColumn();
                        $stRows = $pdo->prepare(
                            $reportSelect . " WHERE {$where} ORDER BY r.id DESC LIMIT {$lim} OFFSET {$off}"
                        );
                        $stRows->execute($params);
                        $reportsRows = $stRows->fetchAll(PDO::FETCH_ASSOC);
                    }
                } catch (PDOException $e) {
                    $reportsError = $e->getMessage();
                }
                $reportsTotalPages = $reportIdFilter > 0
                    ? 1
                    : ($reportsTotal > 0 ? (int) ceil($reportsTotal / $perPage) : 1);
                include __DIR__ . '/admin_sections/reports.php';
                break;

            case 'ops':
                include __DIR__ . '/admin_sections/ops_tools.php';
                break;

            case 'hotlinks':
                $perPage = 50;
                $page = isset($_GET['p']) && is_numeric($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
                $offset = ($page - 1) * $perPage;
                $hotlinkTotal = 0;
                $hotlinkRows = [];
                $hotlinkError = null;
                try {
                    $hotlinkTotal = (int) $pdo->query('SELECT COUNT(*) FROM hotlink_log')->fetchColumn();
                    $lim = (int) $perPage;
                    $off = (int) $offset;
                    $hotlinkRows = $pdo->query(
                        "SELECT * FROM hotlink_log ORDER BY id DESC LIMIT {$lim} OFFSET {$off}"
                    )->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $hotlinkError = $e->getMessage();
                }
                $hotlinkTotalPages = $hotlinkTotal > 0 ? (int) ceil($hotlinkTotal / $perPage) : 1;
                include __DIR__ . '/admin_sections/hotlinks.php';
                break;

            case 'files':
                // Filter by quarantine status (optional: pending only); exclude trashed by default
                $fileStatusFilter = isset($_GET['status']) && $_GET['status'] === 'pending' ? 'pending' : 'all';
                $filesSql = "
                    SELECT f.*, u.username
                    FROM files f
                    LEFT JOIN users u ON f.user_id = u.id
                    WHERE f.deleted_at IS NULL
                ";
                if ($fileStatusFilter === 'pending') {
                    $filesSql .= " AND f.quarantine_status = 'pending'";
                }
                $filesSql .= " ORDER BY f.upload_date DESC";
                $stmt = $pdo->query($filesSql);
                $allFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $fileIds = array_values(array_filter(array_map('intval', array_column($allFiles, 'id'))));
                $reportsByFileId = [];
                if ($fileIds !== []) {
                    $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
                    $stRep = $pdo->prepare("SELECT id, file_id, status FROM file_reports WHERE file_id IN ({$placeholders}) ORDER BY id DESC");
                    $stRep->execute($fileIds);
                    while ($row = $stRep->fetch(PDO::FETCH_ASSOC)) {
                        $fid = (int) $row['file_id'];
                        if (!isset($reportsByFileId[$fid])) {
                            $reportsByFileId[$fid] = [];
                        }
                        $reportsByFileId[$fid][] = $row;
                    }
                }
                foreach ($allFiles as $k => $f) {
                    $allFiles[$k]['report_entries'] = $reportsByFileId[(int) $f['id']] ?? [];
                }

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
