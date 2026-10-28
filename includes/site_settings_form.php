<?php
/**
 * Site Settings is split into sidebar categories. Each save updates only that category
 * and keeps every other value from the settings file.
 */

function datadock_site_settings_groups(): array {
    return [
        'general' => 'General',
        'accounts' => 'Accounts',
        'uploads' => 'Uploads',
        'files' => 'Files',
        'security' => 'Security',
        'identity' => 'Identity',
        'legal' => 'Legal',
        'maintenance' => 'Maintenance',
    ];
}

function datadock_site_settings_group_description(string $group): string {
    $descriptions = [
        'general' => 'Site name, contact email, logo, and the theme guests see.',
        'accounts' => 'Registration, email rules, passwords, and authenticator policy.',
        'uploads' => 'Size limits, guest uploads, allowed file types, and scanning.',
        'files' => 'Storage location, folders, public access, and download limits.',
        'security' => 'Login protection, sessions, admin access, and hotlink rules.',
        'identity' => 'OpenID Connect, LDAP, and sign-in through a reverse proxy.',
        'legal' => 'Terms of service and the public privacy notice.',
        'maintenance' => 'Maintenance mode, logging, and disk or quota alerts.',
    ];
    return $descriptions[$group] ?? '';
}

function datadock_load_site_settings_form(array $fileSettings): array {
    $userLimits = $fileSettings['user_limits'] ?? [];
    $userMaxStorage = (int) ($userLimits['max_storage'] ?? 104857600);
    $guestMaxStorage = (int) ($fileSettings['guest_uploads']['max_storage'] ?? 5242880);
    $maxFileSize = (int) ($fileSettings['max_file_size'] ?? 5242880);
    $rememberDeviceCfg = is_array($fileSettings['remember_device'] ?? null) ? $fileSettings['remember_device'] : [];
    $rememberDeviceCookieDays = (int) ($rememberDeviceCfg['cookie_days'] ?? 30);
    if ($rememberDeviceCookieDays < 1) {
        $rememberDeviceCookieDays = 1;
    }
    if ($rememberDeviceCookieDays > 365) {
        $rememberDeviceCookieDays = 365;
    }
    $securityHeadersMode = strtolower(trim((string) ($fileSettings['security_headers_mode'] ?? 'off')));
    if (!in_array($securityHeadersMode, ['off', 'recommended', 'strict'], true)) {
        $securityHeadersMode = 'off';
    }
    $filetypePolicy = is_array($fileSettings['filetype_policy'] ?? null) ? $fileSettings['filetype_policy'] : [];
    $filetypeMode = (string) ($filetypePolicy['mode'] ?? 'off');
    if (!in_array($filetypeMode, ['off', 'allow', 'deny'], true)) {
        $filetypeMode = 'off';
    }
    $passwordRules = is_array($fileSettings['password_rules'] ?? null) ? $fileSettings['password_rules'] : [];
    $passwordMinLength = (int) ($passwordRules['min_length'] ?? 6);
    if ($passwordMinLength < 6) {
        $passwordMinLength = 6;
    }
    $downloadQuota = is_array($fileSettings['download_quota'] ?? null) ? $fileSettings['download_quota'] : [];
    $downloadQuotaUserBytes = (int) ($downloadQuota['max_bytes_per_user'] ?? 0);
    $downloadQuotaFileBytes = (int) ($downloadQuota['max_bytes_per_file'] ?? 0);
    $uploadScan = is_array($fileSettings['upload_scan'] ?? null) ? $fileSettings['upload_scan'] : [];
    $uploadScanMode = (string) ($uploadScan['mode'] ?? 'off');
    if (!in_array($uploadScanMode, ['off', 'command', 'clamd'], true)) {
        $uploadScanMode = 'off';
    }
    $identityCfg = is_array($fileSettings['identity'] ?? null) ? $fileSettings['identity'] : [];
    $oidcCfg = is_array($identityCfg['oidc'] ?? null) ? $identityCfg['oidc'] : [];
    $ldapCfg = is_array($identityCfg['ldap'] ?? null) ? $identityCfg['ldap'] : [];
    $forwardCfg = is_array($identityCfg['forward_auth'] ?? null) ? $identityCfg['forward_auth'] : [];
    $ldapEncryption = strtolower(trim((string) ($ldapCfg['encryption'] ?? 'none')));
    if (!in_array($ldapEncryption, ['none', 'starttls', 'ldaps'], true)) {
        $ldapEncryption = 'none';
    }
    $totpPolicy = strtolower(trim((string) ($identityCfg['totp_policy'] ?? 'off')));
    if (!in_array($totpPolicy, ['off', 'optional', 'required_admin'], true)) {
        $totpPolicy = 'off';
    }
    $opsAl = is_array($fileSettings['ops_alerts'] ?? null) ? $fileSettings['ops_alerts'] : [];
    $fileIcons = is_array($fileSettings['file_icons'] ?? null) ? $fileSettings['file_icons'] : [];
    $logLevel = (string) ($fileSettings['log_level'] ?? 'warning');
    if (!in_array($logLevel, ['debug', 'info', 'warning', 'error'], true)) {
        $logLevel = 'warning';
    }

    return [
        'siteName' => (string) ($fileSettings['site_name'] ?? ''),
        'adminContactEmail' => trim((string) ($fileSettings['admin_contact_email'] ?? '')),
        'registrationEnabled' => $fileSettings['registration_enabled'] ?? true,
        'inviteOnlyRegistration' => !empty($fileSettings['invite_only_registration']),
        'enforceUniqueEmail' => $fileSettings['enforce_unique_email'] ?? true,
        'emailVerificationEnabled' => !empty($fileSettings['email_verification_enabled']),
        'maxFileSize' => $maxFileSize,
        'maxFileSizeDisplay' => bytes_to_display($maxFileSize),
        'defaultFileExpiry' => (string) ($fileSettings['default_file_expiry'] ?? 'never'),
        'trashRetentionDays' => (int) ($fileSettings['trash_retention_days'] ?? 30),
        'thumbnailsEnabled' => $fileSettings['thumbnails_enabled'] ?? true,
        'sessionTimeoutMinutes' => (int) ($fileSettings['session_timeout_minutes'] ?? 60),
        'securityHeadersMode' => $securityHeadersMode,
        'rememberDeviceEnabled' => !array_key_exists('enabled', $rememberDeviceCfg) || !empty($rememberDeviceCfg['enabled']),
        'rememberDeviceCookieDays' => $rememberDeviceCookieDays,
        'installWarningEnabled' => $fileSettings['install_warning_enabled'] ?? true,
        'userMaxFilesEnabled' => $userLimits['max_files_enabled'] ?? false,
        'userMaxFiles' => (int) ($userLimits['max_files'] ?? 100),
        'userMaxStorageEnabled' => $userLimits['max_storage_enabled'] ?? false,
        'userMaxStorage' => $userMaxStorage,
        'userMaxStorageDisplay' => bytes_to_display($userMaxStorage),
        'bruteForceEnabled' => $fileSettings['brute_force']['enabled'] ?? true,
        'maxAttempts' => (int) ($fileSettings['brute_force']['max_attempts'] ?? 5),
        'lockoutMinutes' => (int) ($fileSettings['brute_force']['lockout_minutes'] ?? 15),
        'lockoutWindow' => (int) ($fileSettings['brute_force']['lockout_window'] ?? 10),
        'adaptiveCooldownEnabled' => !empty($fileSettings['brute_force']['adaptive_cooldown_enabled']),
        'adaptiveCooldownWindow' => (int) ($fileSettings['brute_force']['adaptive_cooldown_ip_window_minutes'] ?? 60),
        'rateLimitUploadsEnabled' => !empty($fileSettings['rate_limit_uploads']['enabled']),
        'rateLimitWindowMinutes' => (int) ($fileSettings['rate_limit_uploads']['window_minutes'] ?? 1),
        'rateLimitMaxPerIp' => (int) ($fileSettings['rate_limit_uploads']['max_per_ip'] ?? 30),
        'rateLimitMaxPerUser' => (int) ($fileSettings['rate_limit_uploads']['max_per_user'] ?? 60),
        'rewriteFileExtension' => !empty($fileSettings['rewrite_file_extension']),
        'uploadQuarantineEnabled' => !empty($fileSettings['upload_quarantine_enabled']),
        'filetypeMode' => $filetypeMode,
        'filetypeExtensions' => (string) ($filetypePolicy['extensions'] ?? ''),
        'guestUploadsEnabled' => $fileSettings['guest_uploads']['enabled'] ?? false,
        'guestMaxFiles' => (int) ($fileSettings['guest_uploads']['max_files'] ?? 10),
        'guestMaxStorage' => $guestMaxStorage,
        'guestMaxStorageDisplay' => bytes_to_display($guestMaxStorage),
        'maintenanceMode' => $fileSettings['maintenance_mode'] ?? false,
        'readOnlyMode' => !empty($fileSettings['read_only_mode']),
        'debugMode' => $fileSettings['debug_mode'] ?? false,
        'logPath' => trim((string) ($fileSettings['log_path'] ?? '')),
        'logLevel' => $logLevel,
        'logoUrl' => trim((string) ($fileSettings['logo_url'] ?? '')),
        'faviconUrl' => trim((string) ($fileSettings['favicon_url'] ?? '')),
        'welcomeMessage' => trim((string) ($fileSettings['welcome_message'] ?? '')),
        'theme' => (string) ($fileSettings['theme'] ?? 'light'),
        'fileIcons' => $fileIcons,
        'fileIconsJson' => $fileIcons !== [] ? json_encode($fileIcons, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '',
        'tosEnabled' => $fileSettings['tos_enabled'] ?? false,
        'tosText' => trim((string) ($fileSettings['tos_text'] ?? '')),
        'privacyEnabled' => !empty($fileSettings['privacy_enabled']),
        'privacyText' => trim((string) ($fileSettings['privacy_text'] ?? '')),
        'passwordMinLength' => $passwordMinLength,
        'passwordComplexity' => !empty($passwordRules['complexity']),
        'storageBasePath' => trim((string) ($fileSettings['storage_base_path'] ?? '')),
        'trustedProxies' => trim((string) ($fileSettings['trusted_proxies'] ?? '')),
        'publicBrowsingEnabled' => !empty($fileSettings['public_browsing_enabled']),
        'publicGridEnabled' => !empty($fileSettings['public_grid_enabled']),
        'downloadQuotaEnabled' => !empty($downloadQuota['enabled']),
        'downloadQuotaWindow' => (int) ($downloadQuota['window_minutes'] ?? 60),
        'downloadQuotaUserBytes' => $downloadQuotaUserBytes,
        'downloadQuotaFileBytes' => $downloadQuotaFileBytes,
        'downloadQuotaUserDisplay' => bytes_to_display($downloadQuotaUserBytes),
        'downloadQuotaFileDisplay' => bytes_to_display($downloadQuotaFileBytes),
        'uploadScanMode' => $uploadScanMode,
        'uploadScanCommand' => (string) ($uploadScan['command'] ?? ''),
        'uploadScanSocket' => (string) ($uploadScan['socket'] ?? ''),
        'totpPolicy' => $totpPolicy,
        'oidcCfg' => $oidcCfg,
        'oidcEnabled' => !empty($oidcCfg['enabled']),
        'oidcIssuer' => trim((string) ($oidcCfg['issuer'] ?? '')),
        'oidcClientId' => trim((string) ($oidcCfg['client_id'] ?? '')),
        'oidcClientSecret' => (string) ($oidcCfg['client_secret'] ?? ''),
        'oidcRoleClaim' => trim((string) ($oidcCfg['role_claim'] ?? '')) ?: 'groups',
        'oidcAdminGroups' => trim((string) ($oidcCfg['admin_groups'] ?? '')),
        'ldapCfg' => $ldapCfg,
        'ldapEnabled' => !empty($ldapCfg['enabled']),
        'ldapHost' => trim((string) ($ldapCfg['host'] ?? '')),
        'ldapPort' => (int) ($ldapCfg['port'] ?? 389),
        'ldapEncryption' => $ldapEncryption,
        'ldapUserDn' => trim((string) ($ldapCfg['user_dn_template'] ?? '')),
        'ldapBaseDn' => trim((string) ($ldapCfg['base_dn'] ?? '')),
        'ldapBindDn' => trim((string) ($ldapCfg['bind_dn'] ?? '')),
        'ldapBindPassword' => (string) ($ldapCfg['bind_password'] ?? ''),
        'ldapUserFilter' => trim((string) ($ldapCfg['user_filter'] ?? '')) ?: '(uid=%s)',
        'ldapAdminGroup' => trim((string) ($ldapCfg['admin_group'] ?? '')),
        'forwardCfg' => $forwardCfg,
        'forwardEnabled' => !empty($forwardCfg['enabled']),
        'forwardProxies' => trim((string) ($forwardCfg['trusted_proxies'] ?? '')),
        'forwardUserHeader' => trim((string) ($forwardCfg['user_header'] ?? '')) ?: 'Remote-User',
        'forwardGroupHeader' => trim((string) ($forwardCfg['group_header'] ?? '')) ?: 'Remote-Groups',
        'forwardAdminGroups' => trim((string) ($forwardCfg['admin_groups'] ?? '')),
        'deduplicateStorage' => !empty($fileSettings['deduplicate_storage'] ?? true),
        'foldersEnabledSetting' => !isset($fileSettings['folders_enabled']) || !empty($fileSettings['folders_enabled']),
        'tagsEnabledSetting' => !isset($fileSettings['tags_enabled']) || !empty($fileSettings['tags_enabled']),
        'pastesEnabledSetting' => !isset($fileSettings['pastes_enabled']) || !empty($fileSettings['pastes_enabled']),
        'hotlinkLoggingEnabled' => !isset($fileSettings['hotlink_logging_enabled']) || !empty($fileSettings['hotlink_logging_enabled']),
        'hotlinkBlockEnabled' => !empty($fileSettings['hotlink_block_enabled']),
        'hotlinkTrustedHosts' => trim((string) ($fileSettings['hotlink_trusted_hosts'] ?? '')),
        'adminIpAllowlist' => trim((string) ($fileSettings['admin_ip_allowlist'] ?? '')),
        'opsStoragePartitionPercentEnabled' => !empty($opsAl['storage_partition_percent_enabled']),
        'opsStoragePartitionPercentThreshold' => (int) ($opsAl['storage_partition_percent_threshold'] ?? 85),
        'opsUserQuotaPercentEnabled' => !empty($opsAl['user_quota_percent_enabled']),
        'opsUserQuotaPercentThreshold' => (int) ($opsAl['user_quota_percent_threshold'] ?? 90),
    ];
}

/**
 * @param array<string, mixed> $form
 * @param array<string, mixed> $post
 * @return array{form: array<string, mixed>, errors: list<string>, warnings: list<string>}
 */
function datadock_apply_site_settings_group(array $form, string $group, array $post): array {
    $errors = [];
    $warnings = [];

    if ($group === 'general') {
        $form['siteName'] = trim((string) ($post['site_name'] ?? ''));
        $form['adminContactEmail'] = trim((string) ($post['admin_contact_email'] ?? ''));
        $form['logoUrl'] = trim((string) ($post['logo_url'] ?? ''));
        $form['faviconUrl'] = trim((string) ($post['favicon_url'] ?? ''));
        $form['welcomeMessage'] = trim((string) ($post['welcome_message'] ?? ''));
        $form['theme'] = trim((string) ($post['theme'] ?? 'light'));
        $fileIconsJson = trim((string) ($post['file_icons'] ?? ''));
        if ($form['siteName'] === '') {
            $errors[] = '❌ Site name cannot be empty.';
        }
        if ($form['adminContactEmail'] !== '' && !filter_var($form['adminContactEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = '❌ Admin contact email must be a valid email address.';
        }
        require_once __DIR__ . '/themes.php';
        if (!datadock_theme_is_known($form['theme'])) {
            $errors[] = '❌ That theme is not available.';
        }
        if ($fileIconsJson === '') {
            $form['fileIcons'] = [];
            $form['fileIconsJson'] = '';
        } else {
            $decoded = json_decode($fileIconsJson, true);
            if (!is_array($decoded)) {
                $errors[] = '❌ Custom file icons must be a JSON object.';
            } else {
                $form['fileIcons'] = $decoded;
                $form['fileIconsJson'] = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
        }
    } elseif ($group === 'accounts') {
        $form['registrationEnabled'] = isset($post['registration_enabled']);
        $form['inviteOnlyRegistration'] = isset($post['invite_only_registration']);
        $form['emailVerificationEnabled'] = isset($post['email_verification_enabled']);
        $form['enforceUniqueEmail'] = isset($post['enforce_unique_email']);
        $form['passwordMinLength'] = (int) ($post['password_min_length'] ?? 6);
        if ($form['passwordMinLength'] < 6) {
            $form['passwordMinLength'] = 6;
        }
        if ($form['passwordMinLength'] > 128) {
            $form['passwordMinLength'] = 128;
        }
        $form['passwordComplexity'] = isset($post['password_complexity']);
        $form['totpPolicy'] = strtolower(trim((string) ($post['totp_policy'] ?? 'off')));
        if (!in_array($form['totpPolicy'], ['off', 'optional', 'required_admin'], true)) {
            $form['totpPolicy'] = 'off';
        }
    } elseif ($group === 'uploads') {
        $form['maxFileSize'] = form_size_to_bytes($post['max_file_size'] ?? 0, (string) ($post['max_file_size_unit'] ?? 'm'));
        $form['maxFileSizeDisplay'] = bytes_to_display($form['maxFileSize']);
        $form['userMaxFilesEnabled'] = isset($post['user_max_files_enabled']);
        if (isset($post['user_max_files'])) {
            $form['userMaxFiles'] = (int) $post['user_max_files'];
        }
        $form['userMaxStorageEnabled'] = isset($post['user_max_storage_enabled']);
        if (isset($post['user_max_storage']) && $post['user_max_storage'] !== '') {
            $form['userMaxStorage'] = form_size_to_bytes($post['user_max_storage'], (string) ($post['user_max_storage_unit'] ?? 'm'));
            $form['userMaxStorageDisplay'] = bytes_to_display($form['userMaxStorage']);
        }
        $form['guestUploadsEnabled'] = isset($post['guest_uploads_enabled']);
        if (isset($post['guest_max_files'])) {
            $form['guestMaxFiles'] = (int) $post['guest_max_files'];
        }
        if (isset($post['guest_max_storage']) && $post['guest_max_storage'] !== '') {
            $form['guestMaxStorage'] = form_size_to_bytes($post['guest_max_storage'], (string) ($post['guest_max_storage_unit'] ?? 'm'));
            $form['guestMaxStorageDisplay'] = bytes_to_display($form['guestMaxStorage']);
        }
        $form['rateLimitUploadsEnabled'] = isset($post['rate_limit_uploads_enabled']);
        $form['rateLimitWindowMinutes'] = (int) ($post['rate_limit_window_minutes'] ?? 1);
        $form['rateLimitMaxPerIp'] = (int) ($post['rate_limit_max_per_ip'] ?? 30);
        $form['rateLimitMaxPerUser'] = (int) ($post['rate_limit_max_per_user'] ?? 60);
        $form['rewriteFileExtension'] = isset($post['rewrite_file_extension']);
        $form['uploadQuarantineEnabled'] = isset($post['upload_quarantine_enabled']);
        $form['filetypeMode'] = strtolower(trim((string) ($post['filetype_policy_mode'] ?? 'off')));
        if (!in_array($form['filetypeMode'], ['off', 'allow', 'deny'], true)) {
            $form['filetypeMode'] = 'off';
        }
        $form['filetypeExtensions'] = datadock_extension_list_to_storage((string) ($post['filetype_policy_extensions'] ?? ''));
        $form['uploadScanMode'] = strtolower(trim((string) ($post['upload_scan_mode'] ?? 'off')));
        if (!in_array($form['uploadScanMode'], ['off', 'command', 'clamd'], true)) {
            $form['uploadScanMode'] = 'off';
        }
        $form['uploadScanCommand'] = trim((string) ($post['upload_scan_command'] ?? ''));
        $form['uploadScanSocket'] = trim((string) ($post['upload_scan_socket'] ?? ''));
        $form['defaultFileExpiry'] = trim((string) ($post['default_file_expiry'] ?? 'never'));
        $validExpiry = ['1_minute', '30_minutes', '1_hour', '6_hours', '1_day', '1_week', '1_month', '1_year', 'never'];
        if (!in_array($form['defaultFileExpiry'], $validExpiry, true)) {
            $form['defaultFileExpiry'] = 'never';
        }
        $form['thumbnailsEnabled'] = isset($post['thumbnails_enabled']);
        if ($form['maxFileSize'] <= 0) {
            $errors[] = '❌ Max file size must be a positive number.';
        }
        if ($form['userMaxFilesEnabled'] && (int) $form['userMaxFiles'] < 1) {
            $errors[] = '❌ Max files per user must be a positive number.';
        }
        if ($form['userMaxStorageEnabled'] && (int) $form['userMaxStorage'] < 1) {
            $errors[] = '❌ Max storage per user must be a positive number.';
        }
        if ($form['guestUploadsEnabled'] && ((int) $form['guestMaxFiles'] < 1 || (int) $form['guestMaxStorage'] < 1)) {
            $errors[] = '❌ Guest upload settings must be positive integers.';
        }
        $listedExtensions = datadock_normalize_extension_list($form['filetypeExtensions']);
        $cautionHits = array_values(array_intersect($listedExtensions, datadock_caution_upload_extensions()));
        $blockedHits = array_values(array_intersect($listedExtensions, get_forbidden_upload_extensions()));
        if ($cautionHits !== []) {
            $warnings[] = 'These extensions can carry executable or active content: ' . implode(', ', $cautionHits) . '.';
        }
        if ($blockedHits !== []) {
            $warnings[] = 'These extensions stay blocked even if you list them: ' . implode(', ', $blockedHits) . '.';
        }
    } elseif ($group === 'files') {
        $form['storageBasePath'] = trim((string) ($post['storage_base_path'] ?? ''));
        $form['deduplicateStorage'] = !empty($post['deduplicate_storage']);
        $form['foldersEnabledSetting'] = !empty($post['folders_enabled']);
        $form['tagsEnabledSetting'] = !empty($post['tags_enabled']);
        $form['pastesEnabledSetting'] = !empty($post['pastes_enabled']);
        $form['trashRetentionDays'] = (int) ($post['trash_retention_days'] ?? 30);
        if ($form['trashRetentionDays'] < 0) {
            $form['trashRetentionDays'] = 0;
        }
        if ($form['trashRetentionDays'] > 3650) {
            $form['trashRetentionDays'] = 3650;
        }
        $form['publicBrowsingEnabled'] = isset($post['public_browsing_enabled']);
        $form['publicGridEnabled'] = isset($post['public_grid_enabled']);
        $form['downloadQuotaEnabled'] = isset($post['download_quota_enabled']);
        $form['downloadQuotaWindow'] = (int) ($post['download_quota_window'] ?? 60);
        if ($form['downloadQuotaWindow'] < 1) {
            $form['downloadQuotaWindow'] = 1;
        }
        if ($form['downloadQuotaWindow'] > 10080) {
            $form['downloadQuotaWindow'] = 10080;
        }
        $form['downloadQuotaUserBytes'] = form_size_to_bytes($post['download_quota_user'] ?? 0, (string) ($post['download_quota_user_unit'] ?? 'm'));
        $form['downloadQuotaFileBytes'] = form_size_to_bytes($post['download_quota_file'] ?? 0, (string) ($post['download_quota_file_unit'] ?? 'm'));
        $form['downloadQuotaUserDisplay'] = bytes_to_display($form['downloadQuotaUserBytes']);
        $form['downloadQuotaFileDisplay'] = bytes_to_display($form['downloadQuotaFileBytes']);
    } elseif ($group === 'security') {
        $form['bruteForceEnabled'] = isset($post['brute_force_enabled']);
        if (isset($post['max_attempts'])) {
            $form['maxAttempts'] = (int) $post['max_attempts'];
        }
        if (isset($post['lockout_minutes'])) {
            $form['lockoutMinutes'] = (int) $post['lockout_minutes'];
        }
        if (isset($post['lockout_window'])) {
            $form['lockoutWindow'] = (int) $post['lockout_window'];
        }
        $form['adaptiveCooldownEnabled'] = isset($post['adaptive_cooldown_enabled']);
        $form['adaptiveCooldownWindow'] = (int) ($post['adaptive_cooldown_ip_window_minutes'] ?? 60);
        $form['trustedProxies'] = datadock_normalize_trusted_proxies(trim((string) ($post['trusted_proxies'] ?? '')));
        $form['adminIpAllowlist'] = trim((string) ($post['admin_ip_allowlist'] ?? ''));
        if (strlen($form['adminIpAllowlist']) > 4000) {
            $form['adminIpAllowlist'] = substr($form['adminIpAllowlist'], 0, 4000);
        }
        $form['securityHeadersMode'] = strtolower(trim((string) ($post['security_headers_mode'] ?? 'off')));
        if (!in_array($form['securityHeadersMode'], ['off', 'recommended', 'strict'], true)) {
            $form['securityHeadersMode'] = 'off';
        }
        $form['hotlinkLoggingEnabled'] = isset($post['hotlink_logging_enabled']);
        $form['hotlinkBlockEnabled'] = isset($post['hotlink_block_enabled']);
        $form['hotlinkTrustedHosts'] = trim((string) ($post['hotlink_trusted_hosts'] ?? ''));
        $form['sessionTimeoutMinutes'] = (int) ($post['session_timeout_minutes'] ?? 60);
        $form['rememberDeviceEnabled'] = isset($post['remember_device_enabled']);
        $form['rememberDeviceCookieDays'] = (int) ($post['remember_device_cookie_days'] ?? 30);
        if ($form['rememberDeviceCookieDays'] < 1) {
            $form['rememberDeviceCookieDays'] = 1;
        }
        if ($form['rememberDeviceCookieDays'] > 365) {
            $form['rememberDeviceCookieDays'] = 365;
        }
        if ($form['bruteForceEnabled'] && ((int) $form['maxAttempts'] < 1 || (int) $form['lockoutMinutes'] < 1 || (int) $form['lockoutWindow'] < 1)) {
            $errors[] = '❌ Brute force settings must be positive integers.';
        }
        if ($form['sessionTimeoutMinutes'] < 0) {
            $errors[] = '❌ Session timeout cannot be negative.';
        }
        if ($form['adminIpAllowlist'] !== '') {
            require_once __DIR__ . '/access_control.php';
            $adminIpJson = datadock_parse_ip_allowlist_text($form['adminIpAllowlist']);
            if (!datadock_ip_allowlist_allows($adminIpJson, get_client_ip()) && empty($post['admin_ip_allowlist_confirm'])) {
                $errors[] = '❌ Your current IP is not on the admin allowlist. Confirm that you want to save it anyway.';
            }
        }
    } elseif ($group === 'identity') {
        $form['oidcEnabled'] = isset($post['oidc_enabled']);
        $form['oidcIssuer'] = trim((string) ($post['oidc_issuer'] ?? ''));
        $form['oidcClientId'] = trim((string) ($post['oidc_client_id'] ?? ''));
        $form['oidcClientSecret'] = (string) ($post['oidc_client_secret'] ?? '');
        $form['oidcRoleClaim'] = trim((string) ($post['oidc_role_claim'] ?? '')) ?: 'groups';
        $form['oidcAdminGroups'] = trim((string) ($post['oidc_admin_groups'] ?? ''));
        $form['oidcCfg'] = [
            'enabled' => $form['oidcEnabled'],
            'issuer' => $form['oidcIssuer'],
            'client_id' => $form['oidcClientId'],
            'client_secret' => $form['oidcClientSecret'],
            'role_claim' => $form['oidcRoleClaim'],
            'admin_groups' => $form['oidcAdminGroups'],
        ];
        $form['ldapEnabled'] = isset($post['ldap_enabled']);
        $form['ldapHost'] = trim((string) ($post['ldap_host'] ?? ''));
        $form['ldapPort'] = (int) ($post['ldap_port'] ?? 389);
        $form['ldapEncryption'] = strtolower(trim((string) ($post['ldap_encryption'] ?? 'none')));
        if (!in_array($form['ldapEncryption'], ['none', 'starttls', 'ldaps'], true)) {
            $form['ldapEncryption'] = 'none';
        }
        $form['ldapUserDn'] = trim((string) ($post['ldap_user_dn'] ?? ''));
        $form['ldapBaseDn'] = trim((string) ($post['ldap_base_dn'] ?? ''));
        $form['ldapBindDn'] = trim((string) ($post['ldap_bind_dn'] ?? ''));
        $form['ldapBindPassword'] = (string) ($post['ldap_bind_password'] ?? '');
        $form['ldapUserFilter'] = trim((string) ($post['ldap_user_filter'] ?? '')) ?: '(uid=%s)';
        $form['ldapAdminGroup'] = trim((string) ($post['ldap_admin_group'] ?? ''));
        $form['ldapCfg'] = [
            'enabled' => $form['ldapEnabled'],
            'host' => $form['ldapHost'],
            'port' => $form['ldapPort'],
            'encryption' => $form['ldapEncryption'],
            'user_dn_template' => $form['ldapUserDn'],
            'base_dn' => $form['ldapBaseDn'],
            'bind_dn' => $form['ldapBindDn'],
            'bind_password' => $form['ldapBindPassword'],
            'user_filter' => $form['ldapUserFilter'],
            'admin_group' => $form['ldapAdminGroup'],
        ];
        $form['forwardEnabled'] = isset($post['forward_auth_enabled']);
        $form['forwardProxies'] = trim((string) ($post['forward_trusted_proxies'] ?? ''));
        $form['forwardUserHeader'] = trim((string) ($post['forward_user_header'] ?? '')) ?: 'Remote-User';
        $form['forwardGroupHeader'] = trim((string) ($post['forward_group_header'] ?? '')) ?: 'Remote-Groups';
        $form['forwardAdminGroups'] = trim((string) ($post['forward_admin_groups'] ?? ''));
        $form['forwardCfg'] = [
            'enabled' => $form['forwardEnabled'],
            'trusted_proxies' => $form['forwardProxies'],
            'user_header' => $form['forwardUserHeader'],
            'group_header' => $form['forwardGroupHeader'],
            'admin_groups' => $form['forwardAdminGroups'],
        ];
    } elseif ($group === 'legal') {
        $form['tosEnabled'] = isset($post['tos_enabled']);
        $form['tosText'] = trim((string) ($post['tos_text'] ?? ''));
        $form['privacyEnabled'] = isset($post['privacy_enabled']);
        $form['privacyText'] = trim((string) ($post['privacy_text'] ?? ''));
    } elseif ($group === 'maintenance') {
        $form['maintenanceMode'] = isset($post['maintenance_mode']);
        $form['readOnlyMode'] = isset($post['read_only_mode']);
        $form['debugMode'] = isset($post['debug_mode']);
        $form['logPath'] = trim((string) ($post['log_path'] ?? ''));
        $form['logLevel'] = trim((string) ($post['log_level'] ?? 'warning'));
        if (!in_array($form['logLevel'], ['debug', 'info', 'warning', 'error'], true)) {
            $form['logLevel'] = 'warning';
        }
        $form['installWarningEnabled'] = isset($post['install_warning_enabled']);
        $form['opsStoragePartitionPercentEnabled'] = isset($post['ops_storage_partition_percent_enabled']);
        $form['opsStoragePartitionPercentThreshold'] = (int) ($post['ops_storage_partition_percent_threshold'] ?? 85);
        if ($form['opsStoragePartitionPercentThreshold'] < 1) {
            $form['opsStoragePartitionPercentThreshold'] = 1;
        }
        if ($form['opsStoragePartitionPercentThreshold'] > 100) {
            $form['opsStoragePartitionPercentThreshold'] = 100;
        }
        $form['opsUserQuotaPercentEnabled'] = isset($post['ops_user_quota_percent_enabled']);
        $form['opsUserQuotaPercentThreshold'] = (int) ($post['ops_user_quota_percent_threshold'] ?? 90);
        if ($form['opsUserQuotaPercentThreshold'] < 1) {
            $form['opsUserQuotaPercentThreshold'] = 1;
        }
        if ($form['opsUserQuotaPercentThreshold'] > 100) {
            $form['opsUserQuotaPercentThreshold'] = 100;
        }
    } else {
        $errors[] = '❌ Unknown settings category.';
    }

    return ['form' => $form, 'errors' => $errors, 'warnings' => $warnings];
}
