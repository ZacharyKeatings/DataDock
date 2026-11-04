<?php

$secret = '12345678901234567890';
expect_same('base32 round trip', datadock_base32_decode(datadock_base32_encode($secret)), $secret);

$encoded = datadock_base32_encode($secret);
expect_same('rfc 6238 totp at 59s', datadock_totp_code($secret, 59), '287082');
expect_same('rfc 6238 totp at 1111111111s', datadock_totp_code($secret, 1111111111), '050471');
expect_same('rfc 6238 totp at 20000000000s', datadock_totp_code($secret, 20000000000), '353130');
$now = time();
$window = [
    datadock_totp_code($secret, $now - 30),
    datadock_totp_code($secret, $now),
    datadock_totp_code($secret, $now + 30),
];
$wrong = '000000';
if (in_array($wrong, $window, true)) {
    $wrong = '111111';
}
expect_true('current window accepts the matching code', datadock_totp_verify($encoded, $window[1]));
expect_false('a short code is rejected', datadock_totp_verify($encoded, '12345'));
expect_false('the wrong code is rejected', datadock_totp_verify($encoded, $wrong));

expect_true('group claim grants admin', datadock_groups_grant_admin("Admins, Ops", ['ops']));
expect_true('comma-separated claim grants admin', datadock_groups_grant_admin('Admins', 'users, admins'));
expect_false('empty admin groups grant nothing', datadock_groups_grant_admin('', ['Admins']));
expect_false('unlisted group does not grant admin', datadock_groups_grant_admin('Admins', ['Users']));

expect_same('env bool yes', datadock_env_bool(' YES '), true);
expect_same('env bool off', datadock_env_bool('off'), false);
expect_same('empty env bool uses the default', datadock_env_bool('', false), false);
expect_same('unknown env bool uses the default', datadock_env_bool('maybe', true), true);
expect_true('read only flag', datadock_read_only_enabled(['read_only_mode' => 1]));
expect_false('read only off', datadock_read_only_enabled([]));
