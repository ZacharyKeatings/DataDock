#!/usr/bin/env php
<?php
/**
 * Container start for the published image. Keeps settings and uploads on the data
 * volume, and removes install.php once setup has finished so a new container does
 * not offer the installer again.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "DataDock: docker-bootstrap.php is CLI only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/includes/docker_runtime.php';

$dataDir = getenv('DATADOCK_DATA_DIR');
if (!is_string($dataDir) || $dataDir === '') {
    $dataDir = '/var/lib/datadock';
}

datadock_docker_bootstrap($dataDir);
exit(0);
