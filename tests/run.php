<?php
/**
 * DataDock test runner. PHP and MySQL only — no third-party libraries.
 *
 *   php tests/run.php
 *
 * Database tests use DATADOCK_TEST_DB_HOST, DATADOCK_TEST_DB_PORT,
 * DATADOCK_TEST_DB_USER, and DATADOCK_TEST_DB_PASSWORD when those are set
 * (GitHub Actions does this). Otherwise Docker starts a temporary MySQL 8
 * server. The suite creates a datadock_test_* database and drops it afterward.
 * It does not write config/db.php.
 */

date_default_timezone_set('UTC');

$root = dirname(__DIR__);
$passes = 0;
$failures = 0;

function test_pass(string $name): void {
    global $passes;
    $passes++;
    echo "  ok    {$name}\n";
}

function test_fail(string $name, string $detail): void {
    global $failures;
    $failures++;
    echo "  FAIL  {$name}\n        {$detail}\n";
}

function expect_same(string $name, $actual, $expected): void {
    if ($actual === $expected) {
        test_pass($name);
        return;
    }
    test_fail($name, 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}

function expect_true(string $name, bool $actual): void {
    expect_same($name, $actual, true);
}

function expect_false(string $name, bool $actual): void {
    expect_same($name, $actual, false);
}

function expect_contains(string $name, string $haystack, string $needle): void {
    if (strpos($haystack, $needle) !== false) {
        test_pass($name);
        return;
    }
    test_fail($name, 'missing ' . var_export($needle, true) . ' in ' . var_export($haystack, true));
}

function datadock_test_watch(string $root): void {
    $GLOBALS['datadock_test_watch'] = [];
    foreach (['uploads/.htaccess', 'thumbnails/.htaccess', 'config/db.php', 'config/settings.php', 'install.php'] as $rel) {
        $path = $root . '/' . $rel;
        $GLOBALS['datadock_test_watch'][$path] = is_file($path) ? (string) file_get_contents($path) : null;
    }
}

function datadock_test_restore_watch(): void {
    foreach ($GLOBALS['datadock_test_watch'] ?? [] as $path => $contents) {
        if (!is_string($path)) {
            continue;
        }
        if ($contents === null) {
            if (is_file($path)) {
                @unlink($path);
            }
            continue;
        }
        if (!is_file($path) || file_get_contents($path) !== $contents) {
            file_put_contents($path, $contents);
        }
    }
}

function datadock_test_docker_ok(): bool {
    $code = 1;
    exec('docker info >/dev/null 2>&1', $unused, $code);
    return $code === 0;
}

/**
 * @return int Child exit code
 */
function datadock_test_launch_php_container(string $root): int {
    if (!datadock_test_docker_ok()) {
        fwrite(STDERR, "pdo_mysql is not loaded and Docker is not available.\nInstall the php-mysql package, or install Docker.\n");
        return 1;
    }
    echo "pdo_mysql is not loaded. Running the suite in a PHP container.\n";
    $image = 'datadock-test-php';
    $inspect = 1;
    exec('docker image inspect ' . escapeshellarg($image) . ' >/dev/null 2>&1', $unused, $inspect);
    if ($inspect !== 0) {
        echo "Building {$image} (first run compiles pdo_mysql).\n";
        passthru(
            'docker build -t ' . escapeshellarg($image)
            . ' -f ' . escapeshellarg($root . '/tests/php-cli.Dockerfile')
            . ' ' . escapeshellarg($root . '/tests'),
            $build
        );
        if ($build !== 0) {
            return $build === null ? 1 : $build;
        }
    }

    exec('docker network create datadock-test-net >/dev/null 2>&1');
    exec('docker rm -f datadock-test-mysql >/dev/null 2>&1');
    $mysql = 'docker run -d --name datadock-test-mysql --network datadock-test-net'
        . ' -e MYSQL_ROOT_PASSWORD=datadock_test -e MYSQL_ROOT_HOST=% mysql:8.0';
    $mysqlCode = 1;
    exec($mysql, $mysqlOut, $mysqlCode);
    if ($mysqlCode !== 0) {
        fwrite(STDERR, implode("\n", $mysqlOut) . "\n");
        exec('docker network rm datadock-test-net >/dev/null 2>&1');
        return 1;
    }

    $php = 'docker run --rm --network datadock-test-net'
        . ' -v ' . escapeshellarg($root) . ':/app -w /app'
        . ' -e DATADOCK_TEST_DB_HOST=datadock-test-mysql'
        . ' -e DATADOCK_TEST_DB_PORT=3306'
        . ' -e DATADOCK_TEST_DB_USER=root'
        . ' -e DATADOCK_TEST_DB_PASSWORD=datadock_test'
        . ' -e DATADOCK_TEST_IN_CONTAINER=1'
        . ' ' . escapeshellarg($image) . ' php tests/run.php';
    passthru($php, $testCode);
    exec('docker rm -f datadock-test-mysql >/dev/null 2>&1');
    exec('docker network rm datadock-test-net >/dev/null 2>&1');
    return $testCode === null ? 1 : $testCode;
}

function datadock_test_start_local_mysql(): int {
    $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($server === false) {
        throw new RuntimeException('Could not reserve a local port: ' . $errstr);
    }
    $name = stream_socket_get_name($server, false);
    fclose($server);
    $port = (int) substr((string) $name, (int) strrpos((string) $name, ':') + 1);
    exec('docker rm -f datadock-test-mysql >/dev/null 2>&1');
    $cmd = 'docker run -d --name datadock-test-mysql'
        . ' -e MYSQL_ROOT_PASSWORD=datadock_test -e MYSQL_ROOT_HOST=%'
        . ' -p 127.0.0.1:' . $port . ':3306 mysql:8.0';
    $code = 1;
    exec($cmd, $out, $code);
    if ($code !== 0) {
        throw new RuntimeException(trim(implode("\n", $out)) ?: 'docker run mysql failed');
    }
    $GLOBALS['datadock_test_docker_owned'] = true;
    return $port;
}

function datadock_test_wait_server(string $host, int $port, string $user, string $pass): void {
    $deadline = time() + 90;
    $last = 'MySQL did not accept connections';
    while (time() < $deadline) {
        try {
            new PDO(
                "mysql:host={$host};port={$port};charset=utf8mb4",
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            return;
        } catch (Throwable $e) {
            $last = $e->getMessage();
            usleep(500000);
        }
    }
    throw new RuntimeException($last);
}

function datadock_test_open_database(): PDO {
    $host = getenv('DATADOCK_TEST_DB_HOST');
    $host = is_string($host) ? trim($host) : '';
    $user = getenv('DATADOCK_TEST_DB_USER');
    $user = is_string($user) && $user !== '' ? $user : 'root';
    $pass = getenv('DATADOCK_TEST_DB_PASSWORD');
    $pass = $pass === false ? 'datadock_test' : (string) $pass;
    if ($host === '') {
        if (!datadock_test_docker_ok()) {
            throw new RuntimeException('Set DATADOCK_TEST_DB_HOST or start Docker so the suite can launch MySQL.');
        }
        echo "Starting temporary MySQL.\n";
        $port = datadock_test_start_local_mysql();
        $host = '127.0.0.1';
    } else {
        $portEnv = getenv('DATADOCK_TEST_DB_PORT');
        $port = is_string($portEnv) && ctype_digit($portEnv) ? (int) $portEnv : 3306;
    }

    datadock_test_wait_server($host, $port, $user, $pass);
    $db = 'datadock_test_' . bin2hex(random_bytes(4));
    $GLOBALS['datadock_test_drop'] = [$host, $port, $user, $pass, $db];

    require_once dirname(__DIR__) . '/includes/install_lib.php';
    require_once dirname(__DIR__) . '/includes/migrate.php';
    $installed = install_database($host . ';port=' . $port, $user, $pass, $db);
    if ($installed !== true) {
        throw new RuntimeException(is_string($installed) ? $installed : 'install_database failed');
    }
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    run_migrations($pdo);
    return $pdo;
}

function datadock_test_drop_database(): void {
    $drop = $GLOBALS['datadock_test_drop'] ?? null;
    if (!is_array($drop) || count($drop) !== 5) {
        return;
    }
    [$host, $port, $user, $pass, $db] = $drop;
    if (!is_string($db) || !preg_match('/^datadock_test_[a-f0-9]{8}$/', $db)) {
        return;
    }
    try {
        $admin = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            (string) $user,
            (string) $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $admin->exec('DROP DATABASE IF EXISTS `' . $db . '`');
    } catch (Throwable $e) {
        fwrite(STDERR, "Could not drop {$db}: " . $e->getMessage() . "\n");
    }
    $GLOBALS['datadock_test_drop'] = null;
}

function datadock_test_cleanup(): void {
    datadock_test_drop_database();
    datadock_test_restore_watch();
    if (!empty($GLOBALS['datadock_test_docker_owned'])) {
        exec('docker rm -f datadock-test-mysql >/dev/null 2>&1');
        $GLOBALS['datadock_test_docker_owned'] = false;
    }
}

if (!extension_loaded('pdo_mysql') && getenv('DATADOCK_TEST_DB_HOST') === false) {
    exit(datadock_test_launch_php_container($root));
}
if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "pdo_mysql is not loaded. Install the php-mysql package.\n");
    exit(1);
}

register_shutdown_function('datadock_test_cleanup');
datadock_test_watch($root);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * @return int Number of files that failed php -l
 */
function datadock_lint_tree(string $root): int {
    $failed = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || strcasecmp($file->getExtension(), 'php') !== 0) {
            continue;
        }
        $path = $file->getPathname();
        if (preg_match('#(?:^|[/\\\\])\.git[/\\\\]#', $path)) {
            continue;
        }
        $output = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
        if ($code !== 0) {
            $failed++;
            fwrite(STDERR, implode("\n", $output) . "\n");
        }
    }
    return $failed;
}

echo "Syntax\n";
$lintFailures = datadock_lint_tree($root);
if ($lintFailures === 0) {
    echo "  ok    php -l\n";
} else {
    fwrite(STDERR, "  FAIL  php -l ({$lintFailures} file" . ($lintFailures === 1 ? '' : 's') . ")\n");
}

require_once $root . '/includes/functions.php';
require_once $root . '/includes/pagination.php';
require_once $root . '/includes/rate_limit.php';
require_once $root . '/includes/identity.php';
require_once $root . '/includes/settings_loader.php';
require_once $root . '/includes/security_headers.php';

$tests = glob(__DIR__ . '/*.php') ?: [];
sort($tests, SORT_STRING);
foreach ($tests as $test) {
    $base = basename($test);
    if ($base === 'run.php' || $base === 'database.php') {
        continue;
    }
    echo $base . "\n";
    require $test;
}

echo "database.php\n";
try {
    $GLOBALS['datadock_test_pdo'] = datadock_test_open_database();
    require __DIR__ . '/database.php';
} catch (Throwable $e) {
    test_fail('mysql setup', $e->getMessage());
}

datadock_test_cleanup();

echo "\n{$passes} passed, {$failures} failed\n";
exit(($failures + $lintFailures) > 0 ? 1 : 0);
