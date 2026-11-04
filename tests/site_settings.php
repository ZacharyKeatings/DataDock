<?php

require_once dirname(__DIR__) . '/includes/site_settings_form.php';
require_once dirname(__DIR__) . '/includes/accounts.php';

$form = datadock_load_site_settings_form([]);
expect_same('default site name is empty', $form['siteName'], '');
expect_true('registration starts enabled', $form['registrationEnabled'] === true);
expect_false('invites start off', $form['inviteOnlyRegistration']);
expect_true('unique email starts on', $form['enforceUniqueEmail'] === true);
expect_false('email verification starts off', $form['emailVerificationEnabled']);
expect_same('default max file size is 5 MiB', $form['maxFileSize'], 5242880);
expect_same('default expiry is never', $form['defaultFileExpiry'], 'never');
expect_same('trash stays 30 days', $form['trashRetentionDays'], 30);
expect_true('thumbnails start on', $form['thumbnailsEnabled'] === true);
expect_same('session timeout is 60 minutes', $form['sessionTimeoutMinutes'], 60);
expect_same('security headers start off', $form['securityHeadersMode'], 'off');
expect_true('remember device starts on', $form['rememberDeviceEnabled']);
expect_same('remember device lasts 30 days', $form['rememberDeviceCookieDays'], 30);
expect_true('install warning starts on', $form['installWarningEnabled'] === true);
expect_false('per-user file cap starts off', $form['userMaxFilesEnabled']);
expect_same('per-user file cap default', $form['userMaxFiles'], 100);
expect_same('per-user storage default', $form['userMaxStorage'], 104857600);
expect_true('brute force protection starts on', $form['bruteForceEnabled'] === true);
expect_same('brute force attempt cap', $form['maxAttempts'], 5);
expect_same('lockout length', $form['lockoutMinutes'], 15);
expect_same('lockout window', $form['lockoutWindow'], 10);
expect_false('adaptive cooldown starts off', $form['adaptiveCooldownEnabled']);
expect_false('upload rate limit starts off', $form['rateLimitUploadsEnabled']);
expect_same('upload rate window', $form['rateLimitWindowMinutes'], 1);
expect_same('upload rate per address', $form['rateLimitMaxPerIp'], 30);
expect_same('upload rate per account', $form['rateLimitMaxPerUser'], 60);
expect_same('filetype policy starts off', $form['filetypeMode'], 'off');
expect_false('guest uploads start off', $form['guestUploadsEnabled']);
expect_same('guest file cap', $form['guestMaxFiles'], 10);
expect_same('guest storage cap', $form['guestMaxStorage'], 5242880);
expect_false('maintenance starts off', $form['maintenanceMode']);
expect_false('read only starts off', $form['readOnlyMode']);
expect_same('log level starts at warning', $form['logLevel'], 'warning');
expect_same('theme starts light', $form['theme'], 'light');
expect_same('password minimum starts at 6', $form['passwordMinLength'], 6);
expect_false('password complexity starts off', $form['passwordComplexity']);
expect_same('download window starts at 60', $form['downloadQuotaWindow'], 60);
expect_same('upload scan starts off', $form['uploadScanMode'], 'off');
expect_same('totp starts off', $form['totpPolicy'], 'off');
expect_same('oidc role claim default', $form['oidcRoleClaim'], 'groups');
expect_same('ldap port default', $form['ldapPort'], 389);
expect_same('ldap encryption default', $form['ldapEncryption'], 'none');
expect_same('ldap user filter default', $form['ldapUserFilter'], '(uid=%s)');
expect_same('forward user header default', $form['forwardUserHeader'], 'Remote-User');
expect_same('forward group header default', $form['forwardGroupHeader'], 'Remote-Groups');
expect_true('storage dedup starts on', $form['deduplicateStorage']);
expect_true('folders start on', $form['foldersEnabledSetting']);
expect_true('tags start on', $form['tagsEnabledSetting']);
expect_true('pastes start on', $form['pastesEnabledSetting']);
expect_true('hotlink logging starts on', $form['hotlinkLoggingEnabled']);
expect_false('hotlink blocking starts off', $form['hotlinkBlockEnabled']);
expect_same('disk alert threshold', $form['opsStoragePartitionPercentThreshold'], 85);
expect_same('quota alert threshold', $form['opsUserQuotaPercentThreshold'], 90);

$clamped = datadock_load_site_settings_form([
    'security_headers_mode' => 'nope',
    'remember_device' => ['enabled' => false, 'cookie_days' => 0],
    'filetype_policy' => ['mode' => 'all', 'extensions' => 'png'],
    'password_rules' => ['min_length' => 2, 'complexity' => true],
    'upload_scan' => ['mode' => 'virus'],
    'identity' => [
        'totp_policy' => 'always',
        'oidc' => ['role_claim' => ''],
        'ldap' => ['encryption' => 'ssl', 'user_filter' => ''],
        'forward_auth' => ['user_header' => '', 'group_header' => ''],
    ],
    'log_level' => 'verbose',
]);
expect_same('unknown header mode becomes off', $clamped['securityHeadersMode'], 'off');
expect_same('remember days below 1 become 1', $clamped['rememberDeviceCookieDays'], 1);
expect_false('remember device can be turned off', $clamped['rememberDeviceEnabled']);
expect_same('unknown filetype mode becomes off', $clamped['filetypeMode'], 'off');
expect_same('password minimum cannot go below 6', $clamped['passwordMinLength'], 6);
expect_true('password complexity is loaded', $clamped['passwordComplexity']);
expect_same('unknown scan mode becomes off', $clamped['uploadScanMode'], 'off');
expect_same('unknown totp policy becomes off', $clamped['totpPolicy'], 'off');
expect_same('blank oidc claim becomes groups', $clamped['oidcRoleClaim'], 'groups');
expect_same('unknown ldap encryption becomes none', $clamped['ldapEncryption'], 'none');
expect_same('blank ldap filter keeps the default', $clamped['ldapUserFilter'], '(uid=%s)');
expect_same('blank forward user header keeps the default', $clamped['forwardUserHeader'], 'Remote-User');
expect_same('unknown log level becomes warning', $clamped['logLevel'], 'warning');

$longRemember = datadock_load_site_settings_form(['remember_device' => ['cookie_days' => 400]]);
expect_same('remember days above 365 become 365', $longRemember['rememberDeviceCookieDays'], 365);

$general = datadock_apply_site_settings_group($form, 'general', [
    'site_name' => "  O'Hare  ",
    'admin_contact_email' => ' admin@example.com ',
    'logo_url' => ' https://cdn.example/logo.png ',
    'favicon_url' => '/favicon.ico',
    'welcome_message' => ' Welcome ',
    'theme' => 'ocean',
    'file_icons' => '{"pdf":"file-doc"}',
]);
expect_same('general save has no errors', $general['errors'], []);
expect_same('site name is trimmed', $general['form']['siteName'], "O'Hare");
expect_same('contact email is trimmed', $general['form']['adminContactEmail'], 'admin@example.com');
expect_same('theme is stored', $general['form']['theme'], 'ocean');
expect_same('file icon map is decoded', $general['form']['fileIcons'], ['pdf' => 'file-doc']);
expect_same('general save leaves the file cap alone', $general['form']['maxFileSize'], $form['maxFileSize']);

$badGeneral = datadock_apply_site_settings_group($form, 'general', [
    'site_name' => ' ',
    'admin_contact_email' => 'not-an-email',
    'theme' => 'missing-theme',
    'file_icons' => '[',
]);
expect_true('empty site name is rejected', count(array_filter($badGeneral['errors'], static function ($e) {
    return strpos($e, 'Site name') !== false;
})) === 1);
expect_true('bad contact email is rejected', count(array_filter($badGeneral['errors'], static function ($e) {
    return strpos($e, 'email') !== false;
})) === 1);
expect_true('unknown theme is rejected', count(array_filter($badGeneral['errors'], static function ($e) {
    return strpos($e, 'theme') !== false;
})) === 1);
expect_true('broken icon json is rejected', count(array_filter($badGeneral['errors'], static function ($e) {
    return strpos($e, 'JSON') !== false;
})) === 1);

$accounts = datadock_apply_site_settings_group($form, 'accounts', [
    'invite_only_registration' => '1',
    'email_verification_enabled' => '1',
    'password_min_length' => '200',
    'password_complexity' => '1',
    'totp_policy' => 'required_admin',
]);
expect_same('accounts save has no errors', $accounts['errors'], []);
expect_false('unchecked registration is off', $accounts['form']['registrationEnabled']);
expect_true('invite-only registration is on', $accounts['form']['inviteOnlyRegistration']);
expect_true('email verification is on', $accounts['form']['emailVerificationEnabled']);
expect_false('unchecked unique email is off', $accounts['form']['enforceUniqueEmail']);
expect_same('password minimum is capped at 128', $accounts['form']['passwordMinLength'], 128);
expect_true('password complexity is on', $accounts['form']['passwordComplexity']);
expect_same('admin totp can be required', $accounts['form']['totpPolicy'], 'required_admin');
$shortPassword = datadock_apply_site_settings_group($form, 'accounts', ['password_min_length' => '3', 'registration_enabled' => '1']);
expect_same('password minimum posted below 6 becomes 6', $shortPassword['form']['passwordMinLength'], 6);
$badTotp = datadock_apply_site_settings_group($form, 'accounts', ['totp_policy' => 'always', 'registration_enabled' => '1']);
expect_same('unknown totp choice becomes off', $badTotp['form']['totpPolicy'], 'off');

$rules = ['password_rules' => ['min_length' => 8, 'complexity' => true]];
expect_same('password hint describes the rule', datadock_password_hint($rules), 'At least 8 characters, including an uppercase letter, a lowercase letter, and a number.');
expect_true('short password is rejected', is_string(datadock_password_error('Ab1', $rules)));
expect_true('password without a digit is rejected', is_string(datadock_password_error('Abcdefgh', $rules)));
expect_same('strong password is accepted', datadock_password_error('Abcdefg1', $rules), null);

$uploads = datadock_apply_site_settings_group($form, 'uploads', [
    'max_file_size' => '2',
    'max_file_size_unit' => 'm',
    'user_max_files_enabled' => '1',
    'user_max_files' => '25',
    'user_max_storage_enabled' => '1',
    'user_max_storage' => '1',
    'user_max_storage_unit' => 'g',
    'guest_uploads_enabled' => '1',
    'guest_max_files' => '3',
    'guest_max_storage' => '10',
    'guest_max_storage_unit' => 'm',
    'rate_limit_uploads_enabled' => '1',
    'rate_limit_window_minutes' => '15',
    'rate_limit_max_per_ip' => '4',
    'rate_limit_max_per_user' => '8',
    'rewrite_file_extension' => '1',
    'upload_quarantine_enabled' => '1',
    'filetype_policy_mode' => 'allow',
    'filetype_policy_extensions' => '.PNG, html, php',
    'upload_scan_mode' => 'clamd',
    'upload_scan_command' => '/usr/bin/clamscan',
    'upload_scan_socket' => '/var/run/clamav/clamd.ctl',
    'default_file_expiry' => '1_week',
    'thumbnails_enabled' => '1',
]);
expect_same('uploads save has no errors', $uploads['errors'], []);
expect_same('max file size is stored in bytes', $uploads['form']['maxFileSize'], 2 * 1024 * 1024);
expect_same('per-user file cap is stored', $uploads['form']['userMaxFiles'], 25);
expect_same('per-user storage is stored in bytes', $uploads['form']['userMaxStorage'], 1073741824);
expect_same('guest storage is stored in bytes', $uploads['form']['guestMaxStorage'], 10 * 1024 * 1024);
expect_same('filetype list is normalized', $uploads['form']['filetypeExtensions'], 'png, html, php');
expect_same('scan mode is stored', $uploads['form']['uploadScanMode'], 'clamd');
expect_same('default expiry is stored', $uploads['form']['defaultFileExpiry'], '1_week');
expect_true('caution extensions produce a warning', count(array_filter($uploads['warnings'], static function ($w) {
    return strpos($w, 'html') !== false;
})) === 1);
expect_true('blocked extensions produce a warning', count(array_filter($uploads['warnings'], static function ($w) {
    return strpos($w, 'php') !== false;
})) === 1);
expect_same('uploads save leaves the site name alone', $uploads['form']['siteName'], $form['siteName']);

$badUploads = datadock_apply_site_settings_group($form, 'uploads', [
    'max_file_size' => '0',
    'max_file_size_unit' => 'm',
    'user_max_files_enabled' => '1',
    'user_max_files' => '0',
    'user_max_storage_enabled' => '1',
    'user_max_storage' => '0',
    'user_max_storage_unit' => 'm',
    'guest_uploads_enabled' => '1',
    'guest_max_files' => '0',
    'guest_max_storage' => '0',
    'guest_max_storage_unit' => 'm',
    'filetype_policy_mode' => 'sometimes',
    'upload_scan_mode' => 'magic',
    'default_file_expiry' => 'forever',
]);
expect_true('zero max file size is rejected', count($badUploads['errors']) >= 1);
expect_same('unknown filetype mode posted becomes off', $badUploads['form']['filetypeMode'], 'off');
expect_same('unknown scan mode posted becomes off', $badUploads['form']['uploadScanMode'], 'off');
expect_same('unknown expiry becomes never', $badUploads['form']['defaultFileExpiry'], 'never');
expect_contains('server ini override names both limits', datadock_user_ini_override(1048576, 2097152), 'upload_max_filesize = 1M');
expect_contains('server ini override includes the post limit', datadock_user_ini_override(1048576, 2097152), 'post_max_size = 2M');

$files = datadock_apply_site_settings_group($form, 'files', [
    'storage_base_path' => ' /data/dock ',
    'deduplicate_storage' => '1',
    'folders_enabled' => '1',
    'public_browsing_enabled' => '1',
    'public_grid_enabled' => '1',
    'download_quota_enabled' => '1',
    'download_quota_window' => '0',
    'download_quota_user' => '50',
    'download_quota_user_unit' => 'm',
    'download_quota_file' => '1',
    'download_quota_file_unit' => 'g',
    'trash_retention_days' => '-4',
]);
expect_same('files save has no errors', $files['errors'], []);
expect_same('storage path is trimmed', $files['form']['storageBasePath'], '/data/dock');
expect_true('dedup stays on when posted', $files['form']['deduplicateStorage']);
expect_false('omitted tags switch turns tags off', $files['form']['tagsEnabledSetting']);
expect_false('omitted pastes switch turns pastes off', $files['form']['pastesEnabledSetting']);
expect_true('public browsing is on', $files['form']['publicBrowsingEnabled']);
expect_true('public grid is on', $files['form']['publicGridEnabled']);
expect_same('download window below 1 becomes 1', $files['form']['downloadQuotaWindow'], 1);
expect_same('per-account download budget', $files['form']['downloadQuotaUserBytes'], 50 * 1024 * 1024);
expect_same('per-file download budget', $files['form']['downloadQuotaFileBytes'], 1073741824);
expect_same('negative trash retention becomes 0', $files['form']['trashRetentionDays'], 0);
$longTrash = datadock_apply_site_settings_group($form, 'files', ['trash_retention_days' => '99999', 'download_quota_window' => '99999']);
expect_same('trash retention is capped at 3650 days', $longTrash['form']['trashRetentionDays'], 3650);
expect_same('download window is capped at 10080 minutes', $longTrash['form']['downloadQuotaWindow'], 10080);

$_SERVER['REMOTE_ADDR'] = '198.51.100.10';
$securityBlocked = datadock_apply_site_settings_group($form, 'security', [
    'brute_force_enabled' => '1',
    'max_attempts' => '0',
    'lockout_minutes' => '15',
    'lockout_window' => '10',
    'session_timeout_minutes' => '-1',
    'admin_ip_allowlist' => '203.0.113.5',
    'security_headers_mode' => 'loose',
    'remember_device_cookie_days' => '0',
    'trusted_proxies' => '10.0.0.0/8, nope',
]);
expect_true('bad brute force numbers are rejected', count(array_filter($securityBlocked['errors'], static function ($e) {
    return strpos($e, 'Brute force') !== false;
})) === 1);
expect_true('negative session timeout is rejected', count(array_filter($securityBlocked['errors'], static function ($e) {
    return strpos($e, 'Session timeout') !== false;
})) === 1);
expect_true('allowlist that excludes the current address needs confirmation', count(array_filter($securityBlocked['errors'], static function ($e) {
    return strpos($e, 'allowlist') !== false;
})) === 1);
expect_same('unknown header mode posted becomes off', $securityBlocked['form']['securityHeadersMode'], 'off');
expect_same('remember days posted below 1 become 1', $securityBlocked['form']['rememberDeviceCookieDays'], 1);
expect_same('invalid proxy entries are dropped', $securityBlocked['form']['trustedProxies'], '10.0.0.0/8');

$security = datadock_apply_site_settings_group($form, 'security', [
    'brute_force_enabled' => '1',
    'max_attempts' => '3',
    'lockout_minutes' => '20',
    'lockout_window' => '5',
    'adaptive_cooldown_enabled' => '1',
    'adaptive_cooldown_ip_window_minutes' => '30',
    'trusted_proxies' => '127.0.0.1',
    'admin_ip_allowlist' => str_repeat('a', 4001),
    'admin_ip_allowlist_confirm' => '1',
    'security_headers_mode' => 'strict',
    'hotlink_logging_enabled' => '1',
    'hotlink_block_enabled' => '1',
    'hotlink_trusted_hosts' => 'cdn.example',
    'session_timeout_minutes' => '15',
    'remember_device_enabled' => '1',
    'remember_device_cookie_days' => '14',
]);
expect_same('confirmed security save has no errors', $security['errors'], []);
expect_same('attempt cap is stored', $security['form']['maxAttempts'], 3);
expect_true('adaptive cooldown is on', $security['form']['adaptiveCooldownEnabled']);
expect_same('header mode strict is stored', $security['form']['securityHeadersMode'], 'strict');
expect_true('hotlink blocking is on', $security['form']['hotlinkBlockEnabled']);
expect_same('hotlink hosts are stored', $security['form']['hotlinkTrustedHosts'], 'cdn.example');
expect_same('admin allowlist is truncated', strlen($security['form']['adminIpAllowlist']), 4000);
expect_same('session timeout is stored', $security['form']['sessionTimeoutMinutes'], 15);

$identity = datadock_apply_site_settings_group($form, 'identity', [
    'oidc_enabled' => '1',
    'oidc_issuer' => ' https://idp.example/realms/dock ',
    'oidc_client_id' => ' dock ',
    'oidc_client_secret' => 's3cret',
    'oidc_role_claim' => '',
    'oidc_admin_groups' => 'Admins',
    'ldap_enabled' => '1',
    'ldap_host' => ' ldap.example ',
    'ldap_port' => '636',
    'ldap_encryption' => 'tls',
    'ldap_user_dn' => 'uid=%s,dc=example,dc=com',
    'ldap_base_dn' => 'dc=example,dc=com',
    'ldap_bind_dn' => 'cn=bind,dc=example,dc=com',
    'ldap_bind_password' => 'bind-secret',
    'ldap_user_filter' => '',
    'ldap_admin_group' => 'cn=admins,dc=example,dc=com',
    'forward_auth_enabled' => '1',
    'forward_trusted_proxies' => '10.0.0.1',
    'forward_user_header' => '',
    'forward_group_header' => 'X-Groups',
    'forward_admin_groups' => 'ops',
]);
expect_same('identity save has no errors', $identity['errors'], []);
expect_true('oidc can be enabled', $identity['form']['oidcEnabled']);
expect_same('oidc issuer is trimmed', $identity['form']['oidcIssuer'], 'https://idp.example/realms/dock');
expect_same('blank oidc claim posted becomes groups', $identity['form']['oidcRoleClaim'], 'groups');
expect_same('oidc secret is kept', $identity['form']['oidcClientSecret'], 's3cret');
expect_same('unknown ldap encryption posted becomes none', $identity['form']['ldapEncryption'], 'none');
expect_same('ldap port is stored', $identity['form']['ldapPort'], 636);
expect_same('blank ldap filter posted keeps the default', $identity['form']['ldapUserFilter'], '(uid=%s)');
expect_true('forward auth can be enabled', $identity['form']['forwardEnabled']);
expect_same('blank forward user header posted keeps the default', $identity['form']['forwardUserHeader'], 'Remote-User');
expect_same('forward group header is stored', $identity['form']['forwardGroupHeader'], 'X-Groups');
expect_same('identity config mirrors the oidc fields', $identity['form']['oidcCfg']['client_id'], 'dock');

$legal = datadock_apply_site_settings_group($form, 'legal', [
    'tos_enabled' => '1',
    'tos_text' => " Line one\nLine two ",
    'privacy_enabled' => '1',
    'privacy_text' => 'We keep files you upload.',
]);
expect_same('legal save has no errors', $legal['errors'], []);
expect_true('terms can be enabled', $legal['form']['tosEnabled']);
expect_same('terms text is trimmed', $legal['form']['tosText'], "Line one\nLine two");
expect_true('privacy notice can be enabled', $legal['form']['privacyEnabled']);
expect_same('privacy text is stored', $legal['form']['privacyText'], 'We keep files you upload.');

$maintenance = datadock_apply_site_settings_group($form, 'maintenance', [
    'maintenance_mode' => '1',
    'read_only_mode' => '1',
    'debug_mode' => '1',
    'log_path' => ' /tmp/datadock.log ',
    'log_level' => 'nope',
    'install_warning_enabled' => '1',
    'ops_storage_partition_percent_enabled' => '1',
    'ops_storage_partition_percent_threshold' => '0',
    'ops_user_quota_percent_enabled' => '1',
    'ops_user_quota_percent_threshold' => '500',
]);
expect_same('maintenance save has no errors', $maintenance['errors'], []);
expect_true('maintenance mode is on', $maintenance['form']['maintenanceMode']);
expect_true('read only mode is on', $maintenance['form']['readOnlyMode']);
expect_true('debug mode is on', $maintenance['form']['debugMode']);
expect_same('log path is trimmed', $maintenance['form']['logPath'], '/tmp/datadock.log');
expect_same('unknown log level posted becomes warning', $maintenance['form']['logLevel'], 'warning');
expect_same('disk alert below 1 becomes 1', $maintenance['form']['opsStoragePartitionPercentThreshold'], 1);
expect_same('quota alert above 100 becomes 100', $maintenance['form']['opsUserQuotaPercentThreshold'], 100);

$unknown = datadock_apply_site_settings_group($form, 'billing', []);
expect_true('unknown category is rejected', $unknown['errors'] !== []);
expect_same('unknown category leaves the form unchanged', $unknown['form'], $form);

$saved = $form;
foreach ([
    'general' => [
        'site_name' => "O'Hare",
        'admin_contact_email' => 'admin@example.com',
        'theme' => 'forest',
        'file_icons' => '{"pdf":"file-doc"}',
        'welcome_message' => 'Hello',
    ],
    'accounts' => [
        'registration_enabled' => '1',
        'enforce_unique_email' => '1',
        'password_min_length' => '10',
        'password_complexity' => '1',
        'totp_policy' => 'optional',
    ],
    'uploads' => [
        'max_file_size' => '8',
        'max_file_size_unit' => 'm',
        'thumbnails_enabled' => '1',
        'default_file_expiry' => '1_day',
        'filetype_policy_mode' => 'deny',
        'filetype_policy_extensions' => 'exe',
        'upload_scan_mode' => 'command',
        'upload_scan_command' => 'true',
    ],
    'files' => [
        'storage_base_path' => '/srv/datadock',
        'deduplicate_storage' => '1',
        'folders_enabled' => '1',
        'tags_enabled' => '1',
        'pastes_enabled' => '1',
        'trash_retention_days' => '14',
        'download_quota_enabled' => '1',
        'download_quota_window' => '120',
        'download_quota_user' => '1',
        'download_quota_user_unit' => 'g',
        'download_quota_file' => '100',
        'download_quota_file_unit' => 'm',
    ],
    'security' => [
        'brute_force_enabled' => '1',
        'max_attempts' => '4',
        'lockout_minutes' => '10',
        'lockout_window' => '8',
        'security_headers_mode' => 'recommended',
        'session_timeout_minutes' => '45',
        'remember_device_enabled' => '1',
        'remember_device_cookie_days' => '7',
        'trusted_proxies' => '192.168.0.0/16',
        'hotlink_logging_enabled' => '1',
    ],
    'identity' => [
        'oidc_issuer' => 'https://idp.example',
        'oidc_client_id' => 'dock',
        'oidc_client_secret' => "se'cret",
        'ldap_host' => 'ldap.example',
        'ldap_port' => '389',
        'ldap_encryption' => 'starttls',
        'forward_user_header' => 'Remote-User',
    ],
    'legal' => [
        'tos_enabled' => '1',
        'tos_text' => "Keep it\nshort",
        'privacy_enabled' => '1',
        'privacy_text' => 'Private',
    ],
    'maintenance' => [
        'log_level' => 'info',
        'log_path' => '/var/log/datadock.log',
        'install_warning_enabled' => '1',
        'ops_storage_partition_percent_enabled' => '1',
        'ops_storage_partition_percent_threshold' => '70',
        'ops_user_quota_percent_threshold' => '80',
    ],
] as $group => $post) {
    $applied = datadock_apply_site_settings_group($saved, $group, $post);
    expect_same($group . ' round trip has no errors', $applied['errors'], []);
    $saved = $applied['form'];
}

$path = tempnam(sys_get_temp_dir(), 'ddset');
file_put_contents($path, datadock_export_site_settings_php($saved));
$settings = [];
include $path;
unlink($path);
$reloaded = datadock_load_site_settings_form($settings);
expect_same('saved site name reloads', $reloaded['siteName'], "O'Hare");
expect_same('saved theme reloads', $reloaded['theme'], 'forest');
expect_same('saved file icons reload', $reloaded['fileIcons'], ['pdf' => 'file-doc']);
expect_same('saved password minimum reloads', $reloaded['passwordMinLength'], 10);
expect_true('saved password complexity reloads', $reloaded['passwordComplexity']);
expect_same('saved totp policy reloads', $reloaded['totpPolicy'], 'optional');
expect_same('saved max file size reloads', $reloaded['maxFileSize'], 8 * 1024 * 1024);
expect_same('saved expiry reloads', $reloaded['defaultFileExpiry'], '1_day');
expect_same('saved filetype mode reloads', $reloaded['filetypeMode'], 'deny');
expect_same('saved storage path reloads', $reloaded['storageBasePath'], '/srv/datadock');
expect_same('saved trash retention reloads', $reloaded['trashRetentionDays'], 14);
expect_same('saved download window reloads', $reloaded['downloadQuotaWindow'], 120);
expect_same('saved download budget reloads', $reloaded['downloadQuotaUserBytes'], 1073741824);
expect_same('saved header mode reloads', $reloaded['securityHeadersMode'], 'recommended');
expect_same('saved session timeout reloads', $reloaded['sessionTimeoutMinutes'], 45);
expect_same('saved trusted proxies reload', $reloaded['trustedProxies'], '192.168.0.0/16');
expect_same('saved oidc secret reloads', $reloaded['oidcClientSecret'], "se'cret");
expect_same('saved ldap encryption reloads', $reloaded['ldapEncryption'], 'starttls');
expect_same('saved terms text reloads', $reloaded['tosText'], "Keep it\nshort");
expect_same('saved log level reloads', $reloaded['logLevel'], 'info');
expect_same('saved disk alert reloads', $reloaded['opsStoragePartitionPercentThreshold'], 70);
expect_same('cooldown steps stay on the built-in schedule', $settings['brute_force']['adaptive_cooldown_steps'], [5 => 5, 15 => 15, 30 => 60]);
