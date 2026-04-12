#!/usr/bin/env php
<?php
/**
 * Cron-friendly purge: expired files and trash past retention.
 * Usage: php scripts/datadock-cron-purge.php
 *        php scripts/datadock-cron-purge.php --no-trash
 *        php scripts/datadock-cron-purge.php --no-expired
 *        php scripts/datadock-cron-purge.php --no-share-folders
 */
$base = dirname(__DIR__);
chdir($base);

if (!is_readable($base . '/config/db.php')) {
    fwrite(STDERR, "config/db.php not found. Run from DataDock root.\n");
    exit(1);
}

require_once $base . '/includes/db.php';
require_once $base . '/includes/functions.php';
require_once $base . '/includes/settings_loader.php';
$settings = datadock_load_settings();
require_once $base . '/includes/purge_ops.php';

$doExpired = true;
$doTrash = true;
$doShareFolders = true;
foreach ($argv as $a) {
    if ($a === '--no-trash') {
        $doTrash = false;
    }
    if ($a === '--no-expired') {
        $doExpired = false;
    }
    if ($a === '--no-share-folders') {
        $doShareFolders = false;
    }
}

$exit = 0;
if ($doExpired) {
    $r = datadock_purge_expired_files($pdo);
    echo '[expired] deleted=' . $r['deleted'] . ' freed_bytes=' . $r['freed_bytes'] . "\n";
}
if ($doTrash) {
    $settings = $settings ?? [];
    $retentionDays = (int) ($settings['trash_retention_days'] ?? 30);
    $r = datadock_purge_trash_by_retention($pdo, $retentionDays);
    echo '[trash] deleted=' . $r['deleted'] . ' freed_bytes=' . $r['freed_bytes'] . " (retention_days={$retentionDays})\n";
}
if ($doShareFolders) {
    $n = datadock_purge_expired_share_folders($pdo);
    echo '[share_folders] expired_removed=' . $n . "\n";
}

exit($exit);
