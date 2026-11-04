<?php

expect_true('html is risky inline', is_risky_mime_for_inline('text/html'));
expect_true('html with a charset is risky inline', is_risky_mime_for_inline('text/html; charset=utf-8'));
expect_false('jpeg may be inline', is_risky_mime_for_inline('image/jpeg'));

expect_false('jpeg matches jpeg', mime_extension_mismatch('jpg', 'image/jpeg'));
expect_true('php mime on a jpeg name is a mismatch', mime_extension_mismatch('jpg', 'application/x-httpd-php'));
expect_false('octet-stream is not treated as a mismatch', mime_extension_mismatch('png', 'application/octet-stream'));
expect_false('unknown extensions are not flagged', mime_extension_mismatch('dat', 'text/plain'));

expect_same('extension list is normalized', datadock_extension_list_to_storage(' .PHP, png; jpg '), 'php, png, jpg');

$allow = ['filetype_policy' => ['mode' => 'allow', 'extensions' => 'png, jpg']];
expect_true('allow list accepts png', datadock_extension_allowed_by_policy('png', $allow));
expect_false('allow list rejects gif', datadock_extension_allowed_by_policy('gif', $allow));
expect_false('allow list rejects an empty extension', datadock_extension_allowed_by_policy('', $allow));

$deny = ['filetype_policy' => ['mode' => 'deny', 'extensions' => 'php']];
expect_false('deny list blocks php', datadock_extension_allowed_by_policy('php', $deny));
expect_true('deny list allows png', datadock_extension_allowed_by_policy('png', $deny));
expect_true('off mode allows every extension', datadock_extension_allowed_by_policy('php', ['filetype_policy' => ['mode' => 'off']]));

expect_true('php hidden before another extension is blocked', upload_basename_has_dangerous_extension_segment('evil.php.jpg'));
expect_true('phtml is blocked', upload_basename_has_dangerous_extension_segment('shell.PHTML'));
expect_false('a plain jpeg name is allowed', upload_basename_has_dangerous_extension_segment('photo.jpg'));
expect_false('a name with no dot is allowed', upload_basename_has_dangerous_extension_segment('readme'));

$jpeg = tempnam(sys_get_temp_dir(), 'ddj');
file_put_contents($jpeg, "\xFF\xD8\xFF\xE0" . 'jpeg');
expect_true('jpeg magic bytes pass', upload_strict_image_format_ok($jpeg, 'jpg', 'image/jpeg'));
expect_false('jpeg bytes with a png mime fail', upload_strict_image_format_ok($jpeg, 'jpg', 'image/png'));

$gif = tempnam(sys_get_temp_dir(), 'ddg');
file_put_contents($gif, 'GIF89a' . '<?php echo 1;');
expect_false('gif body named as jpeg fails', upload_strict_image_format_ok($gif, 'jpg', 'image/jpeg'));
expect_true('embedded php in an image is flagged', upload_image_body_has_embedded_script_markers($gif, 'image/gif', 'gif'));
expect_false('non-images skip the script scan', upload_image_body_has_embedded_script_markers($gif, 'text/plain', 'txt'));
unlink($jpeg);
unlink($gif);

[$where, $params] = build_files_list_where([
    'user_id' => 4,
    'search' => 'notes',
    'visibility' => 'public',
    'folder_id' => 0,
]);
expect_contains('file query limits the owner', $where, 'f.user_id = ?');
expect_contains('file query searches name and description', $where, 'original_name LIKE ?');
expect_contains('file query can require public files', $where, 'f.is_public = 1');
expect_contains('folder zero means the root', $where, 'f.folder_id IS NULL');
expect_same('search uses the same term twice', $params, [4, '%notes%', '%notes%']);

[$adminWhere, $adminParams] = build_files_list_where([
    'user_id' => 4,
    'is_admin' => true,
    'exclude_trashed' => false,
    'expiry_filter' => 'expired',
]);
expect_false('admins are not filtered to one user', strpos($adminWhere, 'user_id = ?') !== false);
expect_contains('expired filter uses the database clock', $adminWhere, 'expiry_date <= UTC_TIMESTAMP()');
expect_same('admin query has no bound user', $adminParams, []);
