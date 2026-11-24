<?php

require_once dirname(__DIR__) . '/includes/personal.php';
require_once dirname(__DIR__) . '/includes/updater.php';

expect_true('a known expiry key is accepted', datadock_known_expiry_key('1_week'));
expect_false('an unknown expiry key is rejected', datadock_known_expiry_key('forever'));
expect_same('share preset keeps one-time', datadock_share_preset('onetime'), 'onetime');
expect_same('share preset drops an unknown value', datadock_share_preset('chat'), '');
expect_true('a sealed paste has the browser prefix', datadock_paste_is_sealed('datadock-enc:v1:abc'));
expect_false('plain text is not a sealed paste', datadock_paste_is_sealed('hello'));
expect_same('a notice link stays on this site', datadock_notice_link_absolute('https://example.test/phish'), '');
expect_contains('a relative notice link stays on this site', datadock_notice_link_absolute('edit_file.php?id=4'), 'edit_file.php?id=4');

$atom = datadock_notices_atom('DataDock', 'https://files.example/notices_feed.php?t=abc', 'urn:datadock:notices:abc', [[
    'id' => 9,
    'title' => 'You already have this file',
    'body' => 'notes.txt matches notes.txt.',
    'link_url' => 'edit_file.php?id=3',
    'created_at' => '2026-11-23 12:00:00',
]]);
expect_contains('the notice feed includes the title', $atom, 'You already have this file');
expect_contains('the notice feed keeps a local link', $atom, 'edit_file.php?id=3');
expect_false('the notice feed drops an off-site link', str_contains(datadock_notices_atom('DataDock', 'https://files.example/feed', 'urn:x', [[
    'id' => 1,
    'title' => 'Away',
    'body' => '',
    'link_url' => 'https://evil.example/x',
    'created_at' => '2026-11-23 12:00:00',
]]), 'evil.example'));

$app = sys_get_temp_dir() . '/datadock-app-' . bin2hex(random_bytes(4));
$src = sys_get_temp_dir() . '/datadock-src-' . bin2hex(random_bytes(4));
mkdir($app . '/config', 0700, true);
file_put_contents($app . '/index.php', "<?php echo 'old';\n");
file_put_contents($app . '/config/settings.php', "<?php return ['secret' => 'keep'];\n");
mkdir($src, 0700, true);
file_put_contents($src . '/index.php', "<?php echo 'new';\n");
file_put_contents($src . '/hello.php', "<?php echo 'hi';\n");
mkdir($src . '/config', 0700, true);
file_put_contents($src . '/config/settings.php', "<?php return ['secret' => 'stolen'];\n");
$zipPath = sys_get_temp_dir() . '/datadock-rel-' . bin2hex(random_bytes(4)) . '.zip';
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFile($src . '/index.php', 'DataDock/index.php');
$zip->addFile($src . '/hello.php', 'DataDock/hello.php');
$zip->addFile($src . '/config/settings.php', 'DataDock/config/settings.php');
$zip->close();
$installed = datadock_install_release_zip($zipPath, $app);
expect_true('a release zip installs', !empty($installed['ok']));
expect_contains('a release zip replaces the application', (string) file_get_contents($app . '/index.php'), 'new');
expect_true('a release zip adds a new file', is_file($app . '/hello.php'));
expect_contains('a release zip leaves settings in place', (string) file_get_contents($app . '/config/settings.php'), 'keep');

$evilZip = sys_get_temp_dir() . '/datadock-evil-' . bin2hex(random_bytes(4)) . '.zip';
$evil = new ZipArchive();
$evil->open($evilZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$evil->addFromString('../escape.php', '<?php echo 1;');
$evil->addFromString('index.php', '<?php echo 1;');
$evil->close();
$blocked = datadock_install_release_zip($evilZip, $app);
expect_false('a release zip with an unsafe path is refused', !empty($blocked['ok']));
expect_contains('an unsafe release explains the path', (string) ($blocked['error'] ?? ''), 'unsafe');
expect_false('an unsafe release does not write outside the app', is_file(dirname($app) . '/escape.php'));

datadock_remove_tree($app);
datadock_remove_tree($src);
@unlink($zipPath);
@unlink($evilZip);
