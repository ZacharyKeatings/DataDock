<?php
/**
 * Database and metadata export for backups (DataDock 2.1+).
 */

/**
 * Escape a value for SQL INSERT (MySQL).
 */
function datadock_sql_quote(PDO $pdo, $val): string {
    if ($val === null) {
        return 'NULL';
    }
    return $pdo->quote((string) $val);
}

/**
 * Export all tables as SQL INSERT statements (for small/medium DBs).
 */
function datadock_export_database_sql(PDO $pdo): string {
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $out = "-- DataDock SQL export\n-- Generated: " . gmdate('Y-m-d H:i:s') . " UTC\n";
    $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";
    $out .= "SET sql_mode = '';\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (!$tables) {
        return $out . "-- No tables\n";
    }

    foreach ($tables as $table) {
        $table = (string) $table;
        $out .= "\n-- Table `{$table}`\n";
        $out .= "DELETE FROM `{$table}`;\n";

        $stmt = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            continue;
        }
        $cols = array_keys($rows[0]);
        $colList = '`' . implode('`,`', array_map(static function ($c) {
            return str_replace('`', '``', $c);
        }, $cols)) . '`';

        foreach ($rows as $row) {
            $vals = [];
            foreach ($cols as $c) {
                $vals[] = datadock_sql_quote($pdo, $row[$c] ?? null);
            }
            $out .= 'INSERT INTO `' . str_replace('`', '``', $table) . "` ({$colList}) VALUES (" . implode(',', $vals) . ");\n";
        }
    }

    $out .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
    return $out;
}

/**
 * JSON export of file rows (metadata only).
 *
 * @return string JSON pretty-printed
 */
function datadock_export_files_metadata_json(PDO $pdo): string {
    $stmt = $pdo->query('SELECT * FROM files ORDER BY id');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}
