<?php

expect_true('identical ipv4', datadock_ips_equal('127.0.0.1', '127.0.0.1'));
expect_true('ipv6 loopback forms match', datadock_ips_equal('::1', '0:0:0:0:0:0:0:1'));
expect_false('different addresses', datadock_ips_equal('10.0.0.1', '10.0.0.2'));

expect_true('ipv4 inside a cidr', datadock_ip_in_cidr('192.168.1.10', '192.168.1.0/24'));
expect_false('ipv4 outside a cidr', datadock_ip_in_cidr('10.0.0.1', '192.168.0.0/16'));
expect_true('exact address without a prefix', datadock_ip_in_cidr('10.1.2.3', '10.1.2.3'));
expect_true('ipv6 inside a cidr', datadock_ip_in_cidr('2001:db8::1', '2001:db8::/32'));
expect_false('prefix wider than the address', datadock_ip_in_cidr('192.168.0.1', '192.168.0.0/99'));

expect_true('bare ip is a valid proxy entry', datadock_trusted_proxy_entry_valid('127.0.0.1'));
expect_true('cidr is a valid proxy entry', datadock_trusted_proxy_entry_valid('10.0.0.0/8'));
expect_false('words are not a proxy entry', datadock_trusted_proxy_entry_valid('not-an-ip'));
expect_same(
    'proxy list drops invalid entries',
    datadock_normalize_trusted_proxies("10.0.0.0/8, nope, 127.0.0.1"),
    '10.0.0.0/8, 127.0.0.1'
);
expect_true('trusted proxy list matches a member', datadock_ip_is_trusted_proxy('10.1.2.3', ['10.0.0.0/8']));
expect_false('trusted proxy list rejects outsiders', datadock_ip_is_trusted_proxy('192.168.0.1', ['10.0.0.0/8']));

$saved = $_SERVER;
$_SERVER['HTTPS'] = 'off';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = '';
$_SERVER['SERVER_PORT'] = '80';
expect_false('plain http is not https', datadock_request_is_https());
$_SERVER['SERVER_PORT'] = '443';
expect_true('port 443 counts as https', datadock_request_is_https());
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
expect_true('forwarded proto https counts', datadock_request_is_https());
$_SERVER = $saved;
