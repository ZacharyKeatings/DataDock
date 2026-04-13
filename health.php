<?php
/**
 * JSON health check for reverse proxies and orchestrators (no auth).
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$version = @file_get_contents(__DIR__ . '/VERSION');
$version = $version !== false ? trim($version) : 'unknown';

$payload = [
    'status' => 'ok',
    'service' => 'datadock',
    'version' => $version,
    'time' => gmdate('c'),
];

$dbOk = false;
$configDb = __DIR__ . '/config/db.php';
if (!is_readable($configDb)) {
    $fb = __DIR__ . '/config/db.php.example';
    if (is_readable($fb)) {
        $configDb = $fb;
    }
}
if (is_readable($configDb)) {
    try {
        require_once $configDb;
        /** @var PDO $pdo */
        $pdo->query('SELECT 1');
        $dbOk = true;
    } catch (Throwable $e) {
        $dbOk = false;
    }
} else {
    $dbOk = false;
}

$payload['database'] = $dbOk ? 'ok' : 'error';

if (!$dbOk) {
    $payload['status'] = 'error';
    http_response_code(503);
}

echo json_encode($payload, JSON_UNESCAPED_SLASHES);
