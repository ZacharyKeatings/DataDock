<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_admin();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/purge_ops.php';
require_once __DIR__ . '/includes/pagination.php';
require_once __DIR__ . '/includes/settings_loader.php';
require_once __DIR__ . '/includes/site_settings_form.php';

$pageTitle = "Admin Panel";

$settingsFile = __DIR__ . '/config/settings.php';
$siteName = get_site_name();

$section = $_GET['section'] ?? 'overview';

// Handle POST actions first
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_custom_theme']) || isset($_POST['delete_custom_theme'])) {
        require_once __DIR__ . '/includes/themes.php';
        if (isset($_POST['delete_custom_theme'])) {
            $slug = strtolower(trim((string) ($_POST['theme_slug'] ?? '')));
            if (datadock_delete_custom_theme($slug)) {
                $_SESSION['flash_success'][] = 'Custom theme deleted.';
            } else {
                $_SESSION['flash_error'][] = 'That theme could not be deleted.';
            }
        } else {
            $font = (string) ($_POST['theme_font'] ?? 'system');
            $tokens = [
                'scheme' => (($_POST['theme_scheme'] ?? '') === 'dark') ? 'dark' : 'light',
                'font' => $font,
                'radius' => (string) ($_POST['theme_radius'] ?? '8px'),
                'header' => (string) ($_POST['token_header'] ?? ''),
                'background' => (string) ($_POST['token_background'] ?? ''),
                'text' => (string) ($_POST['token_text'] ?? ''),
                'primary' => (string) ($_POST['token_primary'] ?? ''),
                'secondary' => (string) ($_POST['token_secondary'] ?? ''),
                'accent' => (string) ($_POST['token_accent'] ?? ''),
                'border' => (string) ($_POST['token_border'] ?? ''),
                'box' => (string) ($_POST['token_box'] ?? ''),
                'link' => (string) ($_POST['token_link'] ?? ''),
                'link_hover' => (string) ($_POST['token_link_hover'] ?? ''),
                'button_text' => (string) ($_POST['token_button_text'] ?? ''),
            ];
            $saved = datadock_save_custom_theme(
                (string) ($_POST['theme_name'] ?? ''),
                $tokens,
                (string) ($_POST['theme_extra'] ?? ''),
                strtolower(trim((string) ($_POST['theme_slug'] ?? '')))
            );
            if ($saved['ok']) {
                $_SESSION['flash_success'][] = 'Theme saved. It is now available as a default theme.';
            } else {
                $_SESSION['flash_error'][] = $saved['error'];
            }
        }
        header('Location: admin.php?section=themes');
        exit;
    }

    if (isset($_POST['section']) && $_POST['section'] === 'site') {
        require_once __DIR__ . '/includes/site_settings_form.php';
        $settingsGroupPosted = strtolower(trim((string) ($_POST['settings_group'] ?? '')));
        if (!isset(datadock_site_settings_groups()[$settingsGroupPosted])) {
            $_SESSION['flash_error'][] = '❌ Choose a settings category and save again.';
            header('Location: admin.php?section=site&group=general');
            exit;
        }
        $applied = datadock_apply_site_settings_group(
            datadock_load_site_settings_form(datadock_load_settings_from_file()),
            $settingsGroupPosted,
            $_POST
        );
        foreach ($applied['errors'] as $settingsError) {
            $_SESSION['flash_error'][] = $settingsError;
        }
        if ($applied['errors'] === []) {
            extract($applied['form'], EXTR_OVERWRITE);
            $newName = $siteName;
            foreach ($applied['warnings'] as $settingsWarning) {
                $_SESSION['flash_warning'][] = $settingsWarning;
            }
        }

        if (empty($_SESSION['flash_error'])) {
            if ($settingsGroupPosted === 'accounts' && !$enforceUniqueEmail) {
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
                "    'email_verification_enabled' => " . ($emailVerificationEnabled ? 'true' : 'false') . ",\n" .
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
                "    'privacy_enabled' => " . ($privacyEnabled ? 'true' : 'false') . ",\n" .
                "    'privacy_text' => " . var_export($privacyText, true) . ",\n" .
                "    'password_rules' => [\n" .
                "        'min_length' => $passwordMinLength,\n" .
                "        'complexity' => " . ($passwordComplexity ? 'true' : 'false') . "\n" .
                "    ],\n" .
                "    'storage_base_path' => " . var_export($storageBasePath, true) . ",\n" .
                "    'trusted_proxies' => " . var_export($trustedProxies, true) . ",\n" .
                "    'public_browsing_enabled' => " . ($publicBrowsingEnabled ? 'true' : 'false') . ",\n" .
                "    'public_grid_enabled' => " . ($publicGridEnabled ? 'true' : 'false') . ",\n" .
                "    'download_quota' => [\n" .
                "        'enabled' => " . ($downloadQuotaEnabled ? 'true' : 'false') . ",\n" .
                "        'window_minutes' => $downloadQuotaWindow,\n" .
                "        'max_bytes_per_user' => $downloadQuotaUserBytes,\n" .
                "        'max_bytes_per_file' => $downloadQuotaFileBytes\n" .
                "    ],\n" .
                "    'upload_scan' => [\n" .
                "        'mode' => " . var_export($uploadScanMode, true) . ",\n" .
                "        'command' => " . var_export($uploadScanCommand, true) . ",\n" .
                "        'socket' => " . var_export($uploadScanSocket, true) . "\n" .
                "    ],\n" .
                "    'identity' => [\n" .
                "        'totp_policy' => " . var_export($totpPolicy, true) . ",\n" .
                "        'oidc' => [\n" .
                "            'enabled' => " . ($oidcEnabled ? 'true' : 'false') . ",\n" .
                "            'issuer' => " . var_export($oidcIssuer, true) . ",\n" .
                "            'client_id' => " . var_export($oidcClientId, true) . ",\n" .
                "            'client_secret' => " . var_export($oidcClientSecret, true) . ",\n" .
                "            'role_claim' => " . var_export($oidcRoleClaim, true) . ",\n" .
                "            'admin_groups' => " . var_export($oidcAdminGroups, true) . "\n" .
                "        ],\n" .
                "        'ldap' => [\n" .
                "            'enabled' => " . ($ldapEnabled ? 'true' : 'false') . ",\n" .
                "            'host' => " . var_export($ldapHost, true) . ",\n" .
                "            'port' => $ldapPort,\n" .
                "            'encryption' => " . var_export($ldapEncryption, true) . ",\n" .
                "            'user_dn_template' => " . var_export($ldapUserDn, true) . ",\n" .
                "            'base_dn' => " . var_export($ldapBaseDn, true) . ",\n" .
                "            'bind_dn' => " . var_export($ldapBindDn, true) . ",\n" .
                "            'bind_password' => " . var_export($ldapBindPassword, true) . ",\n" .
                "            'user_filter' => " . var_export($ldapUserFilter, true) . ",\n" .
                "            'admin_group' => " . var_export($ldapAdminGroup, true) . "\n" .
                "        ],\n" .
                "        'forward_auth' => [\n" .
                "            'enabled' => " . ($forwardEnabled ? 'true' : 'false') . ",\n" .
                "            'trusted_proxies' => " . var_export($forwardProxies, true) . ",\n" .
                "            'user_header' => " . var_export($forwardUserHeader, true) . ",\n" .
                "            'group_header' => " . var_export($forwardGroupHeader, true) . ",\n" .
                "            'admin_groups' => " . var_export($forwardAdminGroups, true) . "\n" .
                "        ]\n" .
                "    ],\n" .
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
                "    'filetype_policy' => [\n" .
                "        'mode' => " . var_export($filetypeMode, true) . ",\n" .
                "        'extensions' => " . var_export($filetypeExtensions, true) . "\n" .
                "    ],\n" .
                "    'deduplicate_storage' => " . ($deduplicateStorage ? 'true' : 'false') . ",\n" .
                "    'folders_enabled' => " . ($foldersEnabledSetting ? 'true' : 'false') . ",\n" .
                "    'tags_enabled' => " . ($tagsEnabledSetting ? 'true' : 'false') . ",\n" .
                "    'pastes_enabled' => " . ($pastesEnabledSetting ? 'true' : 'false') . ",\n" .
                "    'hotlink_logging_enabled' => " . ($hotlinkLoggingEnabled ? 'true' : 'false') . ",\n" .
                "    'hotlink_block_enabled' => " . ($hotlinkBlockEnabled ? 'true' : 'false') . ",\n" .
                "    'hotlink_trusted_hosts' => " . var_export($hotlinkTrustedHosts, true) . ",\n" .
                "    'admin_ip_allowlist' => " . var_export($adminIpAllowlist, true) . ",\n" .
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
                if ($settingsGroupPosted === 'files' && $storageBasePath === '') {
                    @unlink(__DIR__ . '/config/.private-storage-skip');
                }
                $_SESSION['flash_success'][] = "✅ Site settings updated successfully.";
            } else {
                $_SESSION['flash_error'][] = "❌ Failed to update site settings.";
            }

            $userIniPath = __DIR__ . '/.user.ini';
            $serverUploadOverride = 0;
            $serverPostOverride = 0;
            if ($settingsGroupPosted === 'uploads') {
                $serverUploadOverride = isset($_POST['server_upload_max']) && $_POST['server_upload_max'] !== '' ? form_size_to_bytes($_POST['server_upload_max'], $_POST['server_upload_max_unit'] ?? 'm') : 0;
                $serverPostOverride = isset($_POST['server_post_max']) && $_POST['server_post_max'] !== '' ? form_size_to_bytes($_POST['server_post_max'], $_POST['server_post_max_unit'] ?? 'm') : 0;
            }
            if ($settingsGroupPosted === 'uploads' && !empty($_POST['server_clear_user_ini']) && file_exists($userIniPath)) {
                if (@unlink($userIniPath)) {
                    $_SESSION['flash_success'][] = "✅ Server override removed (.user.ini deleted).";
                } else {
                    $_SESSION['flash_error'][] = "❌ Could not remove .user.ini. Check permissions.";
                }
            } elseif ($settingsGroupPosted === 'uploads' && ($serverUploadOverride > 0 || $serverPostOverride > 0)) {
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

        header('Location: admin.php?section=site&group=' . rawurlencode($settingsGroupPosted));
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
        $purgedPastes = datadock_purge_expired_pastes($pdo);
        $adminUid = (int) ($_SESSION['user_id'] ?? 0);
        datadock_log_activity($pdo, 'admin_purge_expired', [
            'actor_user_id' => $adminUid > 0 ? $adminUid : null,
            'detail' => ['deleted' => $deletedCount, 'freed_bytes' => $freedBytes, 'pastes' => $purgedPastes],
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
        }
        if ($purgedPastes > 0) {
            $_SESSION['flash_success'][] = "✅ Purged $purgedPastes expired paste(s).";
        }
        if ($deletedCount === 0 && $purgedPastes === 0) {
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
            try {
                $pdo->exec('DELETE FROM pastes');
            } catch (PDOException $e) {
            }
            try {
                $pdo->exec('DELETE FROM user_notices');
            } catch (PDOException $e) {
            }
            try {
                $pdo->exec('DELETE FROM signed_links');
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

        if ($reportId <= 0 || !in_array($action, ['dismiss', 'quarantine', 'delete', 'hold', 'clear_hold'], true)) {
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

        require_once __DIR__ . '/includes/notices.php';
        $fileId = (int) ($report['file_id'] ?? 0);
        $fileName = (string) ($report['original_name'] ?? $report['filename'] ?? ('#' . $fileId));
        $reporterId = (int) ($report['reporter_user_id'] ?? 0);

        if ($action === 'hold' || $action === 'clear_hold') {
            if ($fileId > 0) {
                $pdo->prepare('UPDATE files SET purge_hold = ? WHERE id = ?')->execute([$action === 'hold' ? 1 : 0, $fileId]);
            }
            datadock_log_activity($pdo, $action === 'hold' ? 'purge_hold_set' : 'purge_hold_cleared', [
                'actor_user_id' => $adminUid > 0 ? $adminUid : null,
                'file_id' => $fileId > 0 ? $fileId : null,
                'related_user_id' => $reporterId > 0 ? $reporterId : null,
                'detail' => ['report_id' => $reportId, 'name' => $fileName],
            ]);
            if ($action === 'hold' && $reporterId > 0) {
                datadock_add_notice($pdo, $reporterId, 'report_reviewed', 'A file you reported is held for review', $fileName, 'dashboard.php');
            }
            $_SESSION['flash_success'][] = $action === 'hold'
                ? '✅ File will be skipped by expiry and trash purge until the hold is cleared.'
                : '✅ Purge hold cleared.';
            header('Location: admin.php?section=reports');
            exit;
        }

        if (($report['status'] ?? 'open') !== 'open') {
            $_SESSION['flash_warning'][] = '⚠️ This report is already closed.';
            header('Location: admin.php?section=reports');
            exit;
        }

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
            if ($reporterId > 0) {
                datadock_add_notice($pdo, $reporterId, 'report_reviewed', 'Your report was reviewed', 'Report on ' . $fileName . ' was dismissed.', 'dashboard.php');
            }
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
        if ($reporterId > 0) {
            datadock_add_notice($pdo, $reporterId, 'report_reviewed', 'Your report was reviewed', 'Action on ' . $fileName . ': ' . (string) $actionTaken . '.', 'dashboard.php');
        }
        $_SESSION['flash_success'][] = '✅ Report action applied.';
        header('Location: admin.php?section=reports');
        exit;
    }

    if (isset($_POST['ops_restore_backup'])) {
        require_once __DIR__ . '/includes/full_backup.php';
        $confirm = trim((string) ($_POST['restore_confirm'] ?? ''));
        $upload = $_FILES['backup_archive'] ?? null;
        if ($confirm !== 'REPLACE') {
            $_SESSION['flash_error'][] = 'Type REPLACE to confirm the restore.';
        } elseif (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
            $_SESSION['flash_error'][] = 'Choose a full backup .tar file.';
        } else {
            try {
                datadock_full_backup_restore($pdo, (string) $upload['tmp_name']);
                $still = $pdo->prepare('SELECT id FROM users WHERE id = ?');
                $still->execute([(int) ($_SESSION['user_id'] ?? 0)]);
                if (!$still->fetch()) {
                    unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
                    $_SESSION['flash_success'][] = 'Backup restored. Sign in again with an account from that backup.';
                    header('Location: login.php');
                    exit;
                }
                $_SESSION['flash_success'][] = 'Backup restored. Database rows were replaced and stored files were copied back.';
            } catch (Throwable $e) {
                $_SESSION['flash_error'][] = 'Restore failed. ' . $e->getMessage();
            }
        }
        header('Location: admin.php?section=ops');
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
$settings = datadock_load_settings();
$settingsGroup = strtolower(trim((string) ($_GET['group'] ?? 'general')));
if (!isset(datadock_site_settings_groups()[$settingsGroup])) {
    $settingsGroup = 'general';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section admin-panel">
    <h2 class="page-title">Admin Panel</h2>

    <div class="admin-layout">
        <aside class="admin-sidebar">
            <nav class="sidebar-nav" aria-label="Admin sections">
                <ul>
                    <li><a href="?section=overview"<?= $section === 'overview' ? ' class="active"' : '' ?>>Overview</a></li>
                </ul>
                <p class="sidebar-label">Settings</p>
                <ul>
                    <?php foreach (datadock_site_settings_groups() as $settingsGroupKey => $settingsGroupLabel): ?>
                    <li><a href="?section=site&amp;group=<?= sanitize_data($settingsGroupKey) ?>"<?= $section === 'site' && $settingsGroup === $settingsGroupKey ? ' class="active"' : '' ?>><?= sanitize_data($settingsGroupLabel) ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <p class="sidebar-label">Site</p>
                <ul>
                    <li><a href="?section=themes"<?= $section === 'themes' ? ' class="active"' : '' ?>>Themes</a></li>
                    <li><a href="?section=storage"<?= $section === 'storage' ? ' class="active"' : '' ?>>Storage partitions</a></li>
                </ul>
                <p class="sidebar-label">People and files</p>
                <ul>
                    <li><a href="?section=users"<?= $section === 'users' ? ' class="active"' : '' ?>>User Management</a></li>
                    <li><a href="?section=files"<?= $section === 'files' ? ' class="active"' : '' ?>>File Management</a></li>
                    <li><a href="?section=reports"<?= $section === 'reports' ? ' class="active"' : '' ?>>Reports &amp; moderation</a></li>
                </ul>
                <p class="sidebar-label">Operations</p>
                <ul>
                    <li><a href="?section=hotlinks"<?= $section === 'hotlinks' ? ' class="active"' : '' ?>>Hotlink log</a></li>
                    <li><a href="?section=audit"<?= $section === 'audit' ? ' class="active"' : '' ?>>Activity log</a></li>
                    <li><a href="?section=ops"<?= $section === 'ops' ? ' class="active"' : '' ?>>Backup &amp; integrity</a></li>
                    <li><a href="?section=updater"<?= $section === 'updater' ? ' class="active"' : '' ?>>Updater &amp; Changelog</a></li>
                </ul>
                <ul>
                    <li class="sidebar-nav-danger"><a href="?section=reset"<?= $section === 'reset' ? ' class="active"' : '' ?>>Reset Site</a></li>
                </ul>
            </nav>
            <?php $adminEmail = trim($settings['admin_contact_email'] ?? ''); if (!empty($adminEmail)): ?>
            <div class="admin-contact">
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
                extract(datadock_load_site_settings_form(datadock_load_settings_from_file()), EXTR_OVERWRITE);
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

                include __DIR__ . '/admin_sections/site_settings.php';
                break;

            case 'themes':
                include __DIR__ . '/admin_sections/theme_editor.php';
                break;

            case 'users':
                $userDirectory = $pdo->query('SELECT id, username, quota_max_files, quota_max_bytes FROM users ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);
                $userTotal = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
                $userPager = datadock_page_clamp(datadock_page_request('p', 25), $userTotal);
                $userLim = (int) $userPager['per_page'];
                $userOff = (int) $userPager['offset'];
                $stmt = $pdo->query("
                    SELECT u.id, u.username, u.email, u.role, u.suspended, u.email_verified, u.created_at, u.storage_partition_id,
                        u.quota_max_files, u.quota_max_bytes,
                        COUNT(f.id) AS file_count,
                        COALESCE(SUM(f.filesize), 0) AS total_size
                    FROM users u
                    LEFT JOIN files f ON u.id = f.user_id AND f.deleted_at IS NULL
                    GROUP BY u.id, u.username, u.email, u.role, u.suspended, u.email_verified, u.created_at, u.storage_partition_id,
                        u.quota_max_files, u.quota_max_bytes
                    ORDER BY u.created_at DESC
                    LIMIT {$userLim} OFFSET {$userOff}
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
                                f.quarantine_status,
                                f.purge_hold
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
                $filesWhere = 'f.deleted_at IS NULL';
                if ($fileStatusFilter === 'pending') {
                    $filesWhere .= " AND f.quarantine_status = 'pending'";
                }
                $fileStats = $pdo->query("SELECT COUNT(*) AS c, COALESCE(SUM(f.filesize), 0) AS s FROM files f WHERE $filesWhere")->fetch(PDO::FETCH_ASSOC);
                $fileTotal = (int) ($fileStats['c'] ?? 0);
                $fileTotalSize = (int) ($fileStats['s'] ?? 0);
                $filePager = datadock_page_clamp(datadock_page_request('p', 25), $fileTotal);
                $fileLim = (int) $filePager['per_page'];
                $fileOff = (int) $filePager['offset'];
                $allFiles = $pdo->query("
                    SELECT f.*, u.username
                    FROM files f
                    LEFT JOIN users u ON f.user_id = u.id
                    WHERE $filesWhere
                    ORDER BY f.upload_date DESC
                    LIMIT {$fileLim} OFFSET {$fileOff}
                ")->fetchAll(PDO::FETCH_ASSOC);

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
