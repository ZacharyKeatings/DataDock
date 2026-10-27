<?php
/**
 * Database connection bootstrap. Loads config and runs migrations.
 */
$configDb = __DIR__ . '/../config/db.php';
if (!is_readable($configDb)) {
    $fallback = __DIR__ . '/../config/db.php.example';
    if (is_readable($fallback)) {
        $configDb = $fallback;
    }
}
if (!is_readable($configDb)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit(
        "DataDock: Missing database configuration (config/db.php).\n\n"
        . "With Docker: keep ENTRYPOINT as docker-entrypoint.sh, or copy config/db.php.example to config/db.php.\n"
        . "If you bind-mount the project or config/, ensure config/db.php.example is present and writable, or create config/db.php on the host.\n"
    );
}
if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $pdo = $GLOBALS['pdo'];
} else {
    require_once $configDb;
    if (isset($pdo) && $pdo instanceof PDO) {
        $GLOBALS['pdo'] = $pdo;
    }
}
require_once __DIR__ . '/migrate.php';
run_migrations($pdo);
if (function_exists('datadock_sync_session_user')) {
    datadock_sync_session_user();
}
if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['user_id']) && function_exists('datadock_forward_auth_maybe') && $pdo instanceof PDO) {
    if (!function_exists('datadock_load_settings')) {
        require_once __DIR__ . '/settings_loader.php';
    }
    datadock_forward_auth_maybe($pdo, datadock_load_settings());
}
