<?php
/**
 * Published-image behavior. Apache often cannot see Compose environment variables,
 * so the entrypoint writes config/.docker.php and this file reads it.
 *
 * A source checkout bind-mounted over /var/www/html is not image mode: the entrypoint
 * removes .docker.php and leaves install.php in the project tree.
 */

function datadock_docker_app_root(): string {
    return dirname(__DIR__);
}

/**
 * @return array{image_mode: bool, data_dir: string}
 */
function datadock_docker_runtime(?string $appRoot = null): array {
    $appRoot = $appRoot ?? datadock_docker_app_root();
    $datadockImageMode = false;
    $datadockDataDir = '';
    $path = rtrim($appRoot, '/') . '/config/.docker.php';
    if (is_file($path)) {
        require $path;
    }

    return [
        'image_mode' => (bool) $datadockImageMode,
        'data_dir' => is_string($datadockDataDir) ? rtrim($datadockDataDir, '/') : '',
    ];
}

function datadock_docker_image_mode(?string $appRoot = null): bool {
    return datadock_docker_runtime($appRoot)['image_mode'];
}

function datadock_docker_default_storage_path(?string $appRoot = null): string {
    $runtime = datadock_docker_runtime($appRoot);
    if (!$runtime['image_mode']) {
        return '';
    }
    return $runtime['data_dir'];
}

/**
 * Fill an empty storage path when the published image is running.
 * A path saved in Site Settings is left alone.
 *
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function datadock_apply_docker_storage_default(array $settings, ?string $appRoot = null): array {
    if (trim((string) ($settings['storage_base_path'] ?? '')) !== '') {
        return $settings;
    }
    $path = datadock_docker_default_storage_path($appRoot);
    if ($path !== '') {
        $settings['storage_base_path'] = $path;
    }
    return $settings;
}

function datadock_settings_file_ready(?string $appRoot = null): bool {
    $path = rtrim($appRoot ?? datadock_docker_app_root(), '/') . '/config/settings.php';
    return is_file($path) && filesize($path) > 0;
}

/**
 * @return 'skipped'|'removed'|'absent'|'failed'
 */
function datadock_retire_web_installer(?string $appRoot = null): string {
    $appRoot = $appRoot ?? datadock_docker_app_root();
    if (!datadock_docker_image_mode($appRoot)) {
        return 'skipped';
    }
    $path = rtrim($appRoot, '/') . '/install.php';
    if (!is_file($path)) {
        return 'absent';
    }
    return @unlink($path) ? 'removed' : 'failed';
}

function datadock_docker_prepare_data_dir(string $dataDir): void {
    $dataDir = rtrim($dataDir, '/');
    $dirs = [
        $dataDir,
        $dataDir . '/uploads',
        $dataDir . '/uploads/avatars',
        $dataDir . '/thumbnails',
    ];
    foreach ($dirs as $dir) {
        if (is_dir($dir)) {
            continue;
        }
        if (!@mkdir($dir, 0750, true) && !is_dir($dir)) {
            fwrite(STDERR, "DataDock FATAL: could not create {$dir}\n");
            exit(1);
        }
    }
}

function datadock_docker_write_runtime_file(string $appRoot, string $dataDir): void {
    $path = rtrim($appRoot, '/') . '/config/.docker.php';
    $php = "<?php\n"
        . "// Generated at container start. Do not edit.\n"
        . '$datadockImageMode = true;' . "\n"
        . '$datadockDataDir = ' . var_export(rtrim($dataDir, '/'), true) . ";\n";
    if (file_put_contents($path, $php) === false) {
        fwrite(STDERR, "DataDock FATAL: could not write {$path}\n");
        exit(1);
    }
    @chmod($path, 0640);
}

/**
 * Point config/settings.php at the data volume so Site Settings survive a new container.
 * The link may dangle until the installer writes the file; PHP creates the target through it.
 */
function datadock_docker_link_settings(string $appRoot, string $dataDir): string {
    $link = rtrim($appRoot, '/') . '/config/settings.php';
    $target = rtrim($dataDir, '/') . '/settings.php';

    if (is_link($link)) {
        if (readlink($link) !== $target && !@unlink($link)) {
            fwrite(STDERR, "DataDock FATAL: could not replace {$link}\n");
            exit(1);
        }
    } elseif (is_file($link)) {
        $targetReady = is_file($target) && filesize($target) > 0;
        if (!$targetReady && !@copy($link, $target)) {
            fwrite(STDERR, "DataDock FATAL: could not copy settings.php onto {$target}\n");
            exit(1);
        }
        if (!@unlink($link)) {
            fwrite(STDERR, "DataDock FATAL: could not replace config/settings.php with the data-volume link\n");
            exit(1);
        }
    } elseif (file_exists($link)) {
        fwrite(STDERR, "DataDock FATAL: config/settings.php exists and is not a file\n");
        exit(1);
    }

    if (!is_link($link) && !@symlink($target, $link)) {
        fwrite(STDERR, "DataDock FATAL: could not link {$link} to {$target}\n");
        exit(1);
    }

    return $target;
}

/**
 * When a saved storage path is still empty, keep uploads on the data volume.
 */
function datadock_docker_pin_storage_path(string $appRoot, string $dataDir): void {
    $file = rtrim($appRoot, '/') . '/config/settings.php';
    if (!is_file($file)) {
        return;
    }
    $settings = [];
    include $file;
    $current = trim((string) ($settings['storage_base_path'] ?? ''));
    $dataDir = rtrim($dataDir, '/');
    if ($current !== '' || $dataDir === '') {
        return;
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        return;
    }
    require_once __DIR__ . '/private_storage.php';
    $updated = datadock_patch_storage_base_path_source($raw, $dataDir);
    if (!is_string($updated)) {
        return;
    }
    if (file_put_contents($file, $updated, LOCK_EX) === false) {
        fwrite(STDERR, "DataDock: could not set storage_base_path in settings.php\n");
        return;
    }
    if (PHP_SAPI === 'cli') {
        echo "DataDock: storing uploads in {$dataDir}\n";
    }
}

/**
 * null when the database cannot be reached, false when it is up but has no users.
 */
function datadock_docker_database_has_users(?string $appRoot = null): ?bool {
    $runtime = rtrim($appRoot ?? datadock_docker_app_root(), '/') . '/config/.db-runtime.php';
    if (!is_readable($runtime)) {
        return null;
    }
    $host = null;
    $dbname = null;
    $user = null;
    $pass = null;
    require $runtime;
    if (!is_string($host) || $host === '' || !is_string($dbname) || $dbname === '' || !is_string($user)) {
        return null;
    }
    if (!is_string($pass)) {
        $pass = '';
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8mb4',
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_CONNECT_TIMEOUT => 2,
            ]
        );
    } catch (Throwable $e) {
        return null;
    }

    try {
        $stmt = $pdo->query('SELECT 1 FROM users LIMIT 1');
        if ($stmt === false) {
            return null;
        }
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        $message = $e->getMessage();
        if (str_contains($message, '42S02') || str_contains($message, '1146')) {
            return false;
        }
        return null;
    }
}

/**
 * If the database already has users but the settings file was lost with the container, write defaults.
 */
function datadock_docker_recover_settings_if_database_exists(?string $appRoot = null): bool {
    $appRoot = $appRoot ?? datadock_docker_app_root();
    if (!datadock_docker_image_mode($appRoot) || datadock_settings_file_ready($appRoot)) {
        return datadock_settings_file_ready($appRoot);
    }
    if (datadock_docker_database_has_users($appRoot) !== true) {
        return false;
    }
    require_once __DIR__ . '/functions.php';
    write_default_settings_file('DataDock');
    $dataDir = datadock_docker_runtime($appRoot)['data_dir'];
    if ($dataDir !== '') {
        datadock_docker_pin_storage_path($appRoot, $dataDir);
    }
    if (datadock_settings_file_ready($appRoot)) {
        if (PHP_SAPI === 'cli') {
            echo "DataDock: found an existing database and restored site settings on the data volume.\n";
        } else {
            error_log('DataDock: found an existing database and restored site settings on the data volume.');
        }
        return true;
    }
    return false;
}

function datadock_announce_installer_retired(): void {
    $state = datadock_retire_web_installer();
    if ($state === 'removed') {
        fwrite(STDOUT, "Removed install.php. Recreating the container will not bring it back.\n");
    } elseif ($state === 'absent') {
        fwrite(STDOUT, "install.php is already gone. Recreating the container will not bring it back.\n");
    } elseif ($state === 'failed') {
        fwrite(STDERR, "Could not remove install.php. The next container start will remove it.\n");
    }
}

function datadock_docker_bootstrap(string $dataDir, int $dbWaitSeconds = 10): void {
    $dataDir = rtrim($dataDir, '/');
    $appRoot = datadock_docker_app_root();
    if ($dataDir === '' || $dataDir[0] !== '/') {
        fwrite(STDERR, "DataDock FATAL: DATADOCK_DATA_DIR must be an absolute path\n");
        exit(1);
    }

    datadock_docker_prepare_data_dir($dataDir);
    datadock_docker_write_runtime_file($appRoot, $dataDir);
    datadock_docker_link_settings($appRoot, $dataDir);
    echo "DataDock: persistent data directory is {$dataDir}\n";

    $runtimeFile = $appRoot . '/config/.db-runtime.php';
    if (!datadock_settings_file_ready($appRoot) && is_readable($runtimeFile) && $dbWaitSeconds > 0) {
        $attempts = $dbWaitSeconds;
        for ($i = 0; $i < $attempts; $i++) {
            $state = datadock_docker_database_has_users($appRoot);
            if ($state !== null) {
                break;
            }
            if ($i === 0) {
                fwrite(STDERR, "DataDock: waiting for the database before checking whether setup already finished...\n");
            }
            if ($i + 1 < $attempts) {
                sleep(1);
            }
        }
    }

    datadock_docker_recover_settings_if_database_exists($appRoot);

    if (!datadock_settings_file_ready($appRoot)) {
        echo "DataDock: setup is not finished. install.php is available.\n";
        return;
    }

    datadock_docker_pin_storage_path($appRoot, $dataDir);
    $retired = datadock_retire_web_installer($appRoot);
    if ($retired === 'removed') {
        echo "DataDock: removed install.php. Recreating this container will not bring it back.\n";
    } elseif ($retired === 'absent') {
        echo "DataDock: site is already installed.\n";
    } elseif ($retired === 'failed') {
        fwrite(STDERR, "DataDock: could not remove install.php. The next request will try again.\n");
    }
}
