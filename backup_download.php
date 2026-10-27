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
if ($format === 'full') {
    require_once __DIR__ . '/includes/functions.php';
    require_once __DIR__ . '/includes/full_backup.php';
    $tmp = tempnam(sys_get_temp_dir(), 'ddbak');
    if ($tmp === false) {
        http_response_code(500);
        exit('Could not prepare the backup.');
    }
    $tar = $tmp . '.tar';
    @unlink($tmp);
    try {
        datadock_full_backup_create($pdo, $tar);
    } catch (Throwable $e) {
        @unlink($tar);
        http_response_code(500);
        exit('Could not build the backup.');
    }
    header('Content-Type: application/x-tar');
    header('Content-Disposition: attachment; filename="datadock_full_backup_' . gmdate('Y-m-d_His') . '.tar"');
    header('Content-Length: ' . filesize($tar));
    readfile($tar);
    @unlink($tar);
    exit;
}

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
