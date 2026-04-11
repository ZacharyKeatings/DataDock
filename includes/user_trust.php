<?php
/**
 * v2.3.0 — User-facing activity, storage snapshots, data export helpers.
 */

require_once __DIR__ . '/access_control.php';

function datadock_user_total_storage_bytes(PDO $pdo, int $userId): int {
    $st = $pdo->prepare('SELECT COALESCE(SUM(filesize), 0) FROM files WHERE user_id = ? AND deleted_at IS NULL');
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}

/**
 * Record at most one storage snapshot per user per hour (for usage-over-time chart).
 */
function datadock_maybe_record_storage_snapshot(PDO $pdo, int $userId): void {
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM user_storage_snapshots WHERE user_id = ? AND recorded_at > UTC_TIMESTAMP() - INTERVAL 1 HOUR');
        $st->execute([$userId]);
        if ((int) $st->fetchColumn() > 0) {
            return;
        }
        $bytes = datadock_user_total_storage_bytes($pdo, $userId);
        $ins = $pdo->prepare('INSERT INTO user_storage_snapshots (user_id, bytes_total, recorded_at) VALUES (?, ?, UTC_TIMESTAMP())');
        $ins->execute([$userId, $bytes]);
    } catch (Throwable $e) {
        // ignore
    }
}

function datadock_log_file_download_event(PDO $pdo, int $fileId, ?string $ip = null): void {
    $ip = $ip !== null && $ip !== '' ? substr($ip, 0, 45) : datadock_client_ip();
    $cc = datadock_client_country_code();
    try {
        $st = $pdo->prepare('INSERT INTO file_download_events (file_id, downloaded_at, ip_address, country_code) VALUES (?, UTC_TIMESTAMP(), ?, ?)');
        $st->execute([$fileId, $ip, $cc]);
    } catch (Throwable $e) {
        // ignore
    }
}
