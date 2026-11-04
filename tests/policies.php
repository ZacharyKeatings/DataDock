<?php

require_once dirname(__DIR__) . '/includes/quotas.php';
require_once dirname(__DIR__) . '/includes/pastes.php';
require_once dirname(__DIR__) . '/includes/email_verify.php';
require_once dirname(__DIR__) . '/includes/upload_scan.php';
require_once dirname(__DIR__) . '/includes/access_control.php';
require_once dirname(__DIR__) . '/includes/hotlink_log.php';
require_once dirname(__DIR__) . '/includes/full_backup.php';
require_once dirname(__DIR__) . '/includes/site_settings_form.php';

expect_true('pastes default to enabled', datadock_pastes_enabled([]));
expect_false('pastes can be turned off', datadock_pastes_enabled(['pastes_enabled' => false]));
expect_same('paste size follows the site limit', datadock_paste_max_bytes(['max_file_size' => 1024]), 1024);
expect_same('never-expiring paste has no deadline', datadock_paste_expiry_utc('never', []), null);
expect_true('one-hour paste expiry is in the future', strtotime((string) datadock_paste_expiry_utc('1_hour', []) . ' UTC') > time());
expect_true('paste token shape', datadock_paste_token_ok(str_repeat('ab', 16)));
expect_false('short paste token is rejected', datadock_paste_token_ok('abc'));
expect_same('paste length counts characters', datadock_paste_strlen('héllo'), 5);

$_SESSION['user_id'] = 7;
$_SESSION['role'] = 'user';
expect_true('owner can delete a paste', datadock_paste_can_delete(['user_id' => 7]));
expect_false('another account cannot delete a paste', datadock_paste_can_delete(['user_id' => 8]));
$_COOKIE['guest_id'] = str_repeat('cd', 16);
expect_true('guest cookie matches the paste', datadock_paste_is_creator(['user_id' => null, 'guest_id' => str_repeat('cd', 16)]));
unset($_SESSION['user_id'], $_SESSION['role'], $_COOKIE['guest_id']);

expect_true(
    'unverified login is blocked when the feature is on',
    datadock_login_blocked_for_email(['email_verified' => 0], ['email_verification_enabled' => true])
);
expect_false('verification off does not block login', datadock_login_blocked_for_email(['email_verified' => 0], []));
expect_same('mail transport is not configured', datadock_mail_transport_ready(), false);
expect_same(
    'verification email reports the missing mailer',
    datadock_dispatch_verification_email('a@b.c', 'abc'),
    ['sent' => false, 'reason' => 'smtp_not_configured', 'token' => 'abc']
);
expect_contains('verification url carries the token', datadock_verification_url('abc'), 'token=abc');

expect_same('unknown scan mode is off', datadock_upload_scan_config(['upload_scan' => ['mode' => 'nope']])['mode'], 'off');
expect_same('scan off passes the file', datadock_upload_scan_file('/tmp/does-not-matter', []), ['passed' => true, 'message' => '']);
expect_false('empty scan command fails', datadock_upload_scan_command('/tmp/x', '')['passed']);

expect_true('ipv4 cidr match', datadock_ipv4_in_cidr('192.168.1.10', '192.168.1.0/24'));
expect_false('ipv4 cidr miss', datadock_ipv4_in_cidr('10.0.0.1', '192.168.1.0/24'));
expect_true('empty allowlist allows everyone', datadock_ip_allowlist_allows(null, '1.2.3.4'));
expect_true('listed address is allowed', datadock_ip_allowlist_allows('["10.0.0.0/8"]', '10.1.2.3'));
expect_false('unlisted address is refused', datadock_ip_allowlist_allows('["10.0.0.1"]', '10.0.0.2'));
expect_contains('allowlist text becomes json', datadock_parse_ip_allowlist_text("10.0.0.1\n10.0.0.1, 10.1.0.0/16"), '10.1.0.0/16');

expect_same('bracketed ipv6 host', hotlink_normalize_host('[2001:db8::1]'), '2001:db8::1');
expect_same('host port is removed', hotlink_normalize_host('Example.COM:8080'), 'example.com');
expect_true('empty referer is trusted', hotlink_referer_is_trusted('', ['localhost']));
expect_false('other site is not trusted', hotlink_referer_is_trusted('https://evil.example/a', ['localhost']));
expect_true('same host is trusted', hotlink_referer_is_trusted('https://localhost/a', ['localhost']));

$statements = datadock_sql_statements("SELECT 1;\n-- skip me\nSELECT 'a;b';");
expect_same('sql splitter keeps two statements', count($statements), 2);
expect_same('sql splitter drops a comment line', $statements[1], "SELECT 'a;b'");
expect_contains('quoted semicolon stays in the statement', $statements[1], 'a;b');

expect_same('settings group count', count(datadock_site_settings_groups()), 8);
expect_contains('uploads group describes file types', datadock_site_settings_group_description('uploads'), 'file types');

$identity = datadock_identity_config(['identity' => ['totp_policy' => 'nope']]);
expect_same('unknown totp policy falls back to off', $identity['totp_policy'], 'off');

$window = datadock_download_quota_config(['download_quota' => ['enabled' => true, 'window_minutes' => 0, 'max_bytes_per_user' => -5]]);
expect_same('download window has a minimum', $window['window_minutes'], 1);
expect_same('negative download budget becomes zero', $window['max_bytes_per_user'], 0);

$savedSite = getenv('DATADOCK_SITE_NAME');
$savedReadOnly = getenv('DATADOCK_READ_ONLY');
putenv('DATADOCK_SITE_NAME=Test Dock');
putenv('DATADOCK_READ_ONLY=1');
$overridden = datadock_apply_env_overrides(['site_name' => 'Old', 'read_only_mode' => false]);
expect_same('env site name overrides the file', $overridden['site_name'], 'Test Dock');
expect_true('env read-only flag overrides the file', $overridden['read_only_mode'] === true);
if ($savedSite === false) {
    putenv('DATADOCK_SITE_NAME');
} else {
    putenv('DATADOCK_SITE_NAME=' . $savedSite);
}
if ($savedReadOnly === false) {
    putenv('DATADOCK_READ_ONLY');
} else {
    putenv('DATADOCK_READ_ONLY=' . $savedReadOnly);
}
