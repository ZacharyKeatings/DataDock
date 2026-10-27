<?php
/**
 * Admin download of the activity log as CSV or JSON.
 */
require_once __DIR__ . '/includes/auth.php';
init_session();
require_admin();

require_once __DIR__ . '/includes/db.php';

$format = $_GET['format'] ?? 'csv';
if (!in_array($format, ['csv', 'json'], true)) {
    http_response_code(400);
    exit('Invalid format');
}

$sql = '
    SELECT a.id, a.created_at, a.action, a.actor_user_id, u.username AS actor_username,
           a.actor_guest_id, a.file_id, a.related_user_id, a.detail_json, a.ip_address
    FROM activity_log a
    LEFT JOIN users u ON u.id = a.actor_user_id
    ORDER BY a.id DESC
    LIMIT 100000
';
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$stamp = gmdate('Y-m-d_His');

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="datadock_activity_' . $stamp . '.json"');
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="datadock_activity_' . $stamp . '.csv"');
$out = fopen('php://output', 'w');
if ($out === false) {
    exit;
}
fputcsv($out, ['id', 'created_at', 'action', 'actor_user_id', 'actor_username', 'actor_guest_id', 'file_id', 'related_user_id', 'detail_json', 'ip_address']);
foreach ($rows as $row) {
    fputcsv($out, [
        $row['id'] ?? '',
        $row['created_at'] ?? '',
        $row['action'] ?? '',
        $row['actor_user_id'] ?? '',
        $row['actor_username'] ?? '',
        $row['actor_guest_id'] ?? '',
        $row['file_id'] ?? '',
        $row['related_user_id'] ?? '',
        $row['detail_json'] ?? '',
        $row['ip_address'] ?? '',
    ]);
}
fclose($out);
exit;
