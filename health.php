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
if (is_readable(__DIR__ . '/config/db.php')) {
    try {
        require_once __DIR__ . '/config/db.php';
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
