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
$plainJpeg = tempnam(sys_get_temp_dir(), 'ddq');
file_put_contents($plainJpeg, "\xFF\xD8\xFF" . str_repeat('A', 200) . '<?' . str_repeat('B', 200));
expect_false('compressed image bytes are not treated as php', upload_image_body_has_embedded_script_markers($plainJpeg, 'image/jpeg', 'jpg'));
unlink($plainJpeg);
unlink($jpeg);
unlink($gif);

require_once dirname(__DIR__) . '/includes/ingest_file.php';
$thumbDest = sys_get_temp_dir() . '/datadock-thumb-' . bin2hex(random_bytes(3)) . '.jpg';
expect_false('a missing picture skips the thumbnail', datadock_write_image_thumbnail($thumbDest . '.missing', $thumbDest));
$emptyPicture = tempnam(sys_get_temp_dir(), 'dde');
file_put_contents($emptyPicture, '');
expect_false('an empty picture skips the thumbnail', datadock_write_image_thumbnail($emptyPicture, $thumbDest));
$hugePicture = tempnam(sys_get_temp_dir(), 'ddh');
$hugeHandle = fopen($hugePicture, 'c');
ftruncate($hugeHandle, 33554433);
fclose($hugeHandle);
expect_false('a picture over 32 MB skips the thumbnail', datadock_write_image_thumbnail($hugePicture, $thumbDest));
$tinyJpeg = tempnam(sys_get_temp_dir(), 'ddt');
file_put_contents($tinyJpeg, hex2bin('ffd8ffdb00430003020202020202030202020303030304060404040404080606050609080a0a090809090a0c0f0c0a0b0e0b09090d110d0e0f101011100a0c12131210130f101010ffc9000b080001000101011100ffcc000600101005ffda0008010100003f00d2cf20ffd9'));
$thumbWritten = datadock_write_image_thumbnail($tinyJpeg, $thumbDest);
if (function_exists('imagecreatefromstring')) {
    expect_true('a small jpeg gets a thumbnail', $thumbWritten && is_file($thumbDest));
} else {
    expect_false('a missing image library skips the thumbnail', $thumbWritten);
    expect_false('a skipped thumbnail leaves no file', is_file($thumbDest));
}
unlink($emptyPicture);
unlink($hugePicture);
unlink($tinyJpeg);
if (is_file($thumbDest)) {
    unlink($thumbDest);
}

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
