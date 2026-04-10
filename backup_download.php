<?php
/**
 * Admin-only download of SQL dump or files metadata JSON.
 */
require_once __DIR__ . '/includes/auth.php';
init_session();
require_admin();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/backup_export.php';

$format = $_GET['format'] ?? 'sql';
if ($format === 'files_meta') {
    $json = datadock_export_files_metadata_json($pdo);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="datadock_files_metadata_' . gmdate('Y-m-d') . '.json"');
    echo $json;
    exit;
}

if ($format !== 'sql') {
    http_response_code(400);
    exit('Invalid format');
}

$sql = datadock_export_database_sql($pdo);
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="datadock_backup_' . gmdate('Y-m-d_His') . '.sql"');
echo $sql;
exit;
