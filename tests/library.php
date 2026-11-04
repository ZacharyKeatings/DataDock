<?php

require_once dirname(__DIR__) . '/includes/private_storage.php';
require_once dirname(__DIR__) . '/includes/docker_runtime.php';
require_once dirname(__DIR__) . '/includes/install_lib.php';
require_once dirname(__DIR__) . '/includes/accounts.php';
require_once dirname(__DIR__) . '/includes/pastes.php';
require_once dirname(__DIR__) . '/includes/full_backup.php';
require_once dirname(__DIR__) . '/includes/access_control.php';
require_once dirname(__DIR__) . '/includes/folder_links.php';
require_once dirname(__DIR__) . '/includes/read_only.php';
require_once dirname(__DIR__) . '/includes/hotlink_log.php';
require_once dirname(__DIR__) . '/includes/themes.php';
require_once dirname(__DIR__) . '/includes/email_verify.php';

expect_contains('deny-all htaccess blocks http', datadock_htaccess_deny_all(), 'Require all denied');
expect_same('windows slashes are normalized', datadock_normalize_fs_path('C:\\data\\dock\\'), 'C:/data/dock');
expect_true('a child path is under its parent', datadock_path_is_under('/var/www/html/uploads', '/var/www/html'));
expect_false('the filesystem root is not a storage parent', datadock_path_is_under('/var/www', '/'));
expect_same('public_html resolves to the account home', datadock_private_storage_parent('/home/site/public_html'), '/home/site');
expect_same('a bare document root has no private parent', datadock_private_storage_parent('/'), null);
expect_true('app root points at this checkout', is_dir(datadock_app_root() . '/includes'));

$settingsSource = "<?php\n\$settings = [\n    'storage_base_path' => '',\n];\n";
$patched = datadock_patch_storage_base_path_source($settingsSource, '/srv/datadock');
expect_true('storage path can be written into settings source', is_string($patched) && strpos((string) $patched, "'/srv/datadock'") !== false);

$tree = sys_get_temp_dir() . '/datadock-tree-' . bin2hex(random_bytes(4));
mkdir($tree . '/src/nested', 0755, true);
file_put_contents($tree . '/src/nested/a.txt', 'hello');
expect_true('a directory tree can be copied', datadock_copy_tree($tree . '/src', $tree . '/dest'));
expect_same('copied file keeps its contents', file_get_contents($tree . '/dest/nested/a.txt'), 'hello');
expect_true('a copied tree can be removed', datadock_remove_tree($tree));
expect_false('removed tree is gone', is_dir($tree));

$dockerRoot = sys_get_temp_dir() . '/datadock-docker-' . bin2hex(random_bytes(4));
mkdir($dockerRoot . '/config', 0755, true);
file_put_contents($dockerRoot . '/config/.docker.php', "<?php\n\$datadockImageMode = true;\n\$datadockDataDir = '/var/lib/datadock';\n");
file_put_contents($dockerRoot . '/config/settings.php', "<?php\n\$settings = ['site_name' => 'Temp'];\n");
$runtime = datadock_docker_runtime($dockerRoot);
expect_true('docker runtime reads image mode', $runtime['image_mode']);
expect_same('docker runtime reads the data dir', $runtime['data_dir'], '/var/lib/datadock');
expect_same('image mode fills an empty storage path', datadock_apply_docker_storage_default([], $dockerRoot)['storage_base_path'], '/var/lib/datadock');
expect_same('a saved storage path is left alone', datadock_apply_docker_storage_default(['storage_base_path' => '/keep'], $dockerRoot)['storage_base_path'], '/keep');
expect_true('a non-empty settings file is ready', datadock_settings_file_ready($dockerRoot));
expect_same('installer retirement is skipped outside image mode', datadock_retire_web_installer(sys_get_temp_dir()), 'skipped');
datadock_remove_tree($dockerRoot);

$prefill = install_db_form_prefill();
expect_true('install prefill includes a host', isset($prefill['host']) && is_string($prefill['host']));
expect_true('install prefill includes a database name', isset($prefill['name']) && is_string($prefill['name']));

expect_same('png has a friendly type', get_friendly_filetype('image/png'), 'PNG Image');
expect_same('unknown mime stays unknown', get_friendly_filetype('application/x-datadock'), 'Unknown File Type');
expect_true('risky inline list includes html', in_array('text/html', get_risky_inline_mimes(), true));
expect_same('filetype policy normalizes a bad mode', datadock_filetype_policy(['filetype_policy' => ['mode' => 'nope', 'extensions' => ['.PNG']]])['mode'], 'off');
expect_same('extension list drops blanks', datadock_normalize_extension_list(' .png,, jpg '), ['png', 'jpg']);
expect_true('html is a caution extension', in_array('html', datadock_caution_upload_extensions(), true));
expect_true('php is a forbidden extension', in_array('php', get_forbidden_upload_extensions(), true));
$_SERVER['SCRIPT_NAME'] = '/datadock/index.php';
$_SERVER['PHP_SELF'] = '/datadock/index.php';
expect_same('current page is the script name', get_current_page(), 'index.php');
expect_contains('asset url stays under the install directory', app_asset_url('assets/icons.svg'), '/datadock/assets/icons.svg');
expect_contains('icon svg points at the sprite', icon_svg('upload'), 'icons.svg#icon-upload');
expect_contains('file icon for a pdf name', get_file_icon('application/pdf', 'notes.pdf'), 'file-doc');
expect_contains('rendered icon wraps the svg', render_file_icon('file-doc'), 'file-doc');

expect_same('password policy floor is 6', datadock_password_policy(['password_rules' => ['min_length' => 1]])['min_length'], 6);
expect_same('password policy ceiling is 128', datadock_password_policy(['password_rules' => ['min_length' => 500]])['min_length'], 128);
expect_same('paste download name is sanitized', datadock_paste_download_name('My Notes!'), 'My-Notes.txt');
expect_same('blank paste title becomes paste.txt', datadock_paste_download_name(''), 'paste.txt');
expect_same('never is a paste expiry choice', datadock_paste_expiry_offsets()['never'], null);
$_SERVER['HTTP_HOST'] = 'files.example';
$_SERVER['HTTPS'] = 'on';
expect_contains('paste share url uses the host', datadock_paste_share_url('abc'), 'https://files.example');
$_COOKIE['guest_id'] = str_repeat('ab', 16);
expect_same('existing guest cookie is reused', datadock_paste_guest_id(false), str_repeat('ab', 16));

$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.8, not-an-ip';
expect_same('forwarded chain keeps valid addresses', datadock_forwarded_ip_chain(), ['203.0.113.8']);
unset($_SERVER['HTTP_X_FORWARDED_FOR']);

expect_same('sql comments are stripped', datadock_strip_sql_comments("-- hide\nSELECT 1"), 'SELECT 1');
expect_same('base64url decodes', datadock_b64url_decode('aGVsbG8'), 'hello');
expect_same('short asn1 length is one byte', datadock_asn1_len(4), chr(4));
expect_contains('rsa public key is pem', datadock_rsa_pem("\x01\x02", "\x01\x00\x01"), 'BEGIN PUBLIC KEY');
expect_same('a jwt with the wrong shape is rejected', datadock_jwt_rs256_payload('a.b', [], 'https://idp.example', 'dock', 'nonce'), null);
$_SERVER['HTTP_HOST'] = 'files.example';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/datadock/index.php';
expect_same('public url includes the directory', datadock_public_url('oidc.php'), 'https://files.example/datadock/oidc.php');
$_SERVER['HTTP_X_REQUEST_ID'] = 'abc';
expect_same('request header is read', datadock_request_header('X-Request-Id'), 'abc');

$reporting = error_reporting();
$display = ini_get('display_errors');
require_once dirname(__DIR__) . '/includes/auth.php';
error_reporting($reporting);
ini_set('display_errors', (string) $display);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
$csrf = datadock_csrf_token();
expect_same('csrf token is 64 hex characters', strlen($csrf), 64);
expect_same('csrf token is stable', datadock_csrf_token(), $csrf);
expect_contains('csrf field carries the token', datadock_csrf_field(), $csrf);
$_POST['csrf_token'] = $csrf;
expect_true('matching csrf token is valid', datadock_csrf_is_valid());
$_POST['csrf_token'] = 'nope';
expect_false('wrong csrf token is rejected', datadock_csrf_is_valid());
unset($_POST['csrf_token']);

datadock_session_grant_file_access(42, 60);
expect_true('granted file access is remembered', datadock_session_has_file_access(42));
expect_false('unknown file access is absent', datadock_session_has_file_access(99));
datadock_paste_grant(7);
expect_true('paste unlock is remembered', datadock_paste_unlocked(7));
datadock_folder_link_grant(3);
expect_true('folder link unlock is remembered', datadock_folder_link_unlocked(3));

$_SERVER['HTTP_CF_IPCOUNTRY'] = 'us';
expect_same('country header is normalized', datadock_client_country_code(), 'US');
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'XX';
expect_same('unknown country header is ignored', datadock_client_country_code(), null);

expect_true('anonymous access allows a file without a password', datadock_anonymous_access_allowed(['id' => 1, 'ip_allowlist' => null, 'access_password_hash' => null], false));
expect_false('anonymous access honors an ip allowlist', datadock_anonymous_access_allowed(['id' => 1, 'ip_allowlist' => '["203.0.113.9"]', 'access_password_hash' => null], false));

datadock_block_if_read_only(['read_only_mode' => false]);
expect_true('read only off does not stop the request', true);

$stored = datadock_load_settings_from_file();
if (trim((string) ($stored['admin_ip_allowlist'] ?? '')) === '') {
    expect_true('empty admin allowlist allows the request', datadock_admin_request_ip_allowed());
}

$_SERVER['HTTP_HOST'] = 'files.example';
expect_true('request host is trusted for hotlinks', in_array('files.example', hotlink_trusted_hosts_for_request(['hotlink_trusted_hosts' => 'cdn.example']), true));
expect_true('extra hotlink host is trusted', in_array('cdn.example', hotlink_trusted_hosts_for_request(['hotlink_trusted_hosts' => 'cdn.example']), true));

$emptyTheme = datadock_save_custom_theme('   ', [], '');
expect_false('a blank theme name is rejected', $emptyTheme['ok']);
$reservedTheme = datadock_save_custom_theme('Light', [], '');
expect_false('a built-in theme name is reserved', $reservedTheme['ok']);

$themeDir = datadock_theme_custom_dir();
$manifestPath = $themeDir . '/manifest.json';
$manifestBefore = is_file($manifestPath) ? (string) file_get_contents($manifestPath) : null;
$custom = datadock_save_custom_theme('ZZ Coverage Theme', ['scheme' => 'dark', 'primary' => '#112233'], 'body{color:red}');
expect_true('a custom theme can be saved', $custom['ok']);
expect_true('a saved theme is known', $custom['ok'] && datadock_theme_is_known($custom['slug']));
expect_true('a custom theme can be deleted', $custom['ok'] && datadock_delete_custom_theme($custom['slug']));
if ($manifestBefore === null) {
    if (is_file($manifestPath)) {
        unlink($manifestPath);
    }
} else {
    file_put_contents($manifestPath, $manifestBefore);
}
if ($custom['ok']) {
    @unlink($themeDir . '/' . $custom['slug'] . '.css');
}

expect_true('checkout is not docker image mode', datadock_docker_image_mode() === false);
expect_same('checkout has no default docker storage path', datadock_docker_default_storage_path(), '');
expect_same('session hash is sha256 of the session id', datadock_session_hash(), hash('sha256', session_id()));
expect_false('email verification flag defaults off', datadock_email_verification_enabled([]));
expect_true('builtin theme list includes ocean', isset(datadock_builtin_theme_tokens()['ocean']));
expect_true('theme catalog includes light', isset(datadock_theme_catalog()['light']));
