<?php
/**
 * Expired-file and trash purge (shared by admin UI and cron CLI).
 */
require_once __DIR__ . '/storage.php';

/**
 * @return array{deleted:int,freed_bytes:int,filetype_breakdown:array<string,int>}
 */
function datadock_purge_expired_files(PDO $pdo): array {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('SELECT * FROM files WHERE deleted_at IS NULL AND expiry_date IS NOT NULL AND expiry_date < ?');
    $stmt->execute([$now]);
    $expiredFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $deletedCount = 0;
    $freedBytes = 0;
    $filetypeBreakdown = [];

    foreach ($expiredFiles as $file) {
        $freedBytes += (int) ($file['filesize'] ?? 0);
        $type = $file['filetype'] ?? 'unknown';
        $filetypeBreakdown[$type] = ($filetypeBreakdown[$type] ?? 0) + 1;

        datadock_release_file_storage($pdo, $file);
        $pdo->prepare('DELETE FROM files WHERE id = ?')->execute([$file['id']]);
        $deletedCount++;
    }

    return [
        'deleted' => $deletedCount,
        'freed_bytes' => $freedBytes,
        'filetype_breakdown' => $filetypeBreakdown,
    ];
}

/**
 * @return array{deleted:int,freed_bytes:int}
 */
function datadock_purge_trash_by_retention(PDO $pdo, int $retentionDays): array {
    if ($retentionDays > 0) {
        $stmt = $pdo->prepare('SELECT * FROM files WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)');
        $stmt->execute([$retentionDays]);
    } else {
        $stmt = $pdo->query('SELECT * FROM files WHERE deleted_at IS NOT NULL');
    }
    $trashedFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $deletedCount = 0;
    $freedBytes = 0;
    foreach ($trashedFiles as $file) {
        $freedBytes += (int) ($file['filesize'] ?? 0);
        datadock_release_file_storage($pdo, $file);
        $pdo->prepare('DELETE FROM files WHERE id = ?')->execute([$file['id']]);
        $deletedCount++;
    }

    return ['deleted' => $deletedCount, 'freed_bytes' => $freedBytes];
}
