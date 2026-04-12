#!/usr/bin/env php
<?php
/**
 * Non-interactive install (automation / Docker init). Requires MySQL reachable from this host.
 *
 * Usage:
 *   php scripts/datadock-install.php --db-host=db --db-user=datadock --db-pass=secret --db-name=datadock \
 *     --site-name="DataDock" --admin-user=admin --admin-email=a@b.c --admin-password=secret
 *
 * Environment (optional): DD_DB_HOST, DD_DB_USER, DD_DB_PASS, DD_DB_NAME,
 * DD_SITE_NAME, DD_ADMIN_USER, DD_ADMIN_EMAIL, DD_ADMIN_PASSWORD
 */
$base = dirname(__DIR__);
chdir($base);

require_once $base . '/includes/functions.php';
require_once $base . '/includes/install_lib.php';

function arg(string $name, ?string $default = null): ?string {
    global $argv;
    $prefix = '--' . $name . '=';
    foreach ($argv as $a) {
        if (strpos($a, $prefix) === 0) {
            return substr($a, strlen($prefix));
        }
    }
    $envKey = 'DD_' . strtoupper(str_replace('-', '_', $name));
    $v = getenv($envKey);
    if ($v !== false && $v !== '') {
        return (string) $v;
    }
    return $default;
}

$dbHost = arg('db-host', 'localhost') ?? 'localhost';
$dbUser = arg('db-user', 'root') ?? 'root';
$dbPass = arg('db-pass', '') ?? '';
$dbName = arg('db-name', 'datadock') ?? 'datadock';
$siteName = arg('site-name', 'DataDock') ?? 'DataDock';
$adminUser = arg('admin-user', 'admin') ?? 'admin';
$adminEmail = arg('admin-email', 'admin@localhost') ?? 'admin@localhost';
$adminPass = arg('admin-password', '') ?? '';

if ($adminPass === '' || strlen($adminPass) < 6) {
    fwrite(STDERR, "Provide --admin-password= (min 6 chars) or DD_ADMIN_PASSWORD.\n");
    exit(1);
}

$result = install_database($dbHost, $dbUser, $dbPass, $dbName);
if ($result !== true) {
    fwrite(STDERR, "Database setup failed: $result\n");
    exit(1);
}

create_db_config_file($dbHost, $dbUser, $dbPass, $dbName);
write_default_settings_file($siteName);
secure_config_folder();

foreach (['uploads', 'thumbnails'] as $dir) {
    $path = $base . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

require_once $base . '/config/db.php';
require_once $base . '/includes/migrate.php';
run_migrations($pdo);

$stmt = $pdo->prepare('SELECT id FROM users WHERE role = ? LIMIT 1');
$stmt->execute(['admin']);
if ($stmt->fetch()) {
    fwrite(STDOUT, "Already initialized (admin user exists). Config refreshed.\n");
    exit(0);
}

$hash = password_hash($adminPass, PASSWORD_DEFAULT);
$ins = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
$ins->execute([$adminUser, $adminEmail, $hash]);

fwrite(STDOUT, "OK: DataDock installed. Admin user: {$adminUser}\n");
exit(0);
