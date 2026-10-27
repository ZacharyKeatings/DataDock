<?php
/**
 * Per-user quota overrides and download byte budgets.
 */

function datadock_user_quota_limits(PDO $pdo, int $userId, array $settings): array {
    $site = is_array($settings['user_limits'] ?? null) ? $settings['user_limits'] : [];
    $countOn = !empty($site['max_files_enabled']);
    $bytesOn = !empty($site['max_storage_enabled']);
    $maxFiles = (int) ($site['max_files'] ?? 0);
    $maxBytes = (int) ($site['max_storage'] ?? 0);
    try {
        $stmt = $pdo->prepare('SELECT quota_max_files, quota_max_bytes FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $row = false;
    }
    if ($row) {
        if ($row['quota_max_files'] !== null) {
            $n = (int) $row['quota_max_files'];
            if ($n < 0) {
                $countOn = false;
                $maxFiles = 0;
            } else {
                $countOn = true;
                $maxFiles = $n;
            }
        }
        if ($row['quota_max_bytes'] !== null) {
            $n = (int) $row['quota_max_bytes'];
            if ($n < 0) {
                $bytesOn = false;
                $maxBytes = 0;
            } else {
                $bytesOn = true;
                $maxBytes = $n;
            }
        }
    }
    return [
        'count_enabled' => $countOn,
        'max_files' => $maxFiles,
        'bytes_enabled' => $bytesOn,
        'max_bytes' => $maxBytes,
    ];
}

/**
 * @return string|null Error message when the additional bytes would exceed the account quota.
 */
function datadock_user_quota_error(PDO $pdo, int $userId, array $settings, int $addFiles, int $addBytes): ?string {
    $limits = datadock_user_quota_limits($pdo, $userId, $settings);
    if (!$limits['count_enabled'] && !$limits['bytes_enabled']) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) AS file_count, COALESCE(SUM(filesize), 0) AS total_size FROM files WHERE user_id = ? AND deleted_at IS NULL');
    $stmt->execute([$userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['file_count' => 0, 'total_size' => 0];
    if ($limits['count_enabled'] && $limits['max_files'] >= 0 && ((int) $stats['file_count'] + $addFiles) > $limits['max_files']) {
        return 'That account is over its file limit of ' . $limits['max_files'] . ' files.';
    }
    if ($limits['bytes_enabled'] && $limits['max_bytes'] >= 0 && ((int) $stats['total_size'] + $addBytes) > $limits['max_bytes']) {
        return 'That account is over its storage limit of ' . format_filesize($limits['max_bytes']) . '.';
    }
    return null;
}

function datadock_download_quota_config(array $settings): array {
    $cfg = is_array($settings['download_quota'] ?? null) ? $settings['download_quota'] : [];
    $window = (int) ($cfg['window_minutes'] ?? 60);
    if ($window < 1) {
        $window = 1;
    }
    if ($window > 10080) {
        $window = 10080;
    }
    return [
        'enabled' => !empty($cfg['enabled']),
        'window_minutes' => $window,
        'max_bytes_per_user' => max(0, (int) ($cfg['max_bytes_per_user'] ?? 0)),
        'max_bytes_per_file' => max(0, (int) ($cfg['max_bytes_per_file'] ?? 0)),
    ];
}

/**
 * Refuse the download when the window budget is already spent. Records the bytes when allowed.
 *
 * @return string|null Denial message.
 */
function datadock_download_quota_check(PDO $pdo, array $file, array $settings): ?string {
    $cfg = datadock_download_quota_config($settings);
    if (!$cfg['enabled']) {
        return null;
    }
    $bytes = (int) ($file['filesize'] ?? 0);
    if ($bytes < 0) {
        $bytes = 0;
    }
    $fileId = (int) ($file['id'] ?? 0);
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $ip = function_exists('datadock_client_ip') ? datadock_client_ip() : (function_exists('get_client_ip') ? get_client_ip() : '');
    $ip = substr((string) $ip, 0, 45);
    $window = (int) $cfg['window_minutes'];
    try {
        $pdo->prepare('DELETE FROM download_quota_events WHERE downloaded_at < UTC_TIMESTAMP() - INTERVAL 7 DAY')->execute();
        if ($cfg['max_bytes_per_file'] > 0 && $fileId > 0) {
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(bytes), 0) FROM download_quota_events WHERE file_id = ? AND downloaded_at > UTC_TIMESTAMP() - INTERVAL ' . $window . ' MINUTE');
            $stmt->execute([$fileId]);
            if (((int) $stmt->fetchColumn() + $bytes) > $cfg['max_bytes_per_file']) {
                return 'This file has reached its download limit for now. Try again later.';
            }
        }
        if ($cfg['max_bytes_per_user'] > 0) {
            if ($userId > 0) {
                $stmt = $pdo->prepare('SELECT COALESCE(SUM(bytes), 0) FROM download_quota_events WHERE user_id = ? AND downloaded_at > UTC_TIMESTAMP() - INTERVAL ' . $window . ' MINUTE');
                $stmt->execute([$userId]);
            } else {
                $stmt = $pdo->prepare('SELECT COALESCE(SUM(bytes), 0) FROM download_quota_events WHERE user_id IS NULL AND ip_address = ? AND downloaded_at > UTC_TIMESTAMP() - INTERVAL ' . $window . ' MINUTE');
                $stmt->execute([$ip]);
            }
            if (((int) $stmt->fetchColumn() + $bytes) > $cfg['max_bytes_per_user']) {
                return 'Download limit reached for this account. Try again later.';
            }
        }
        $ins = $pdo->prepare('INSERT INTO download_quota_events (file_id, user_id, ip_address, bytes, downloaded_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())');
        $ins->execute([$fileId, $userId > 0 ? $userId : null, $ip, $bytes]);
    } catch (Throwable $e) {
        return 'Download limit could not be checked. Try again later.';
    }
    return null;
}
