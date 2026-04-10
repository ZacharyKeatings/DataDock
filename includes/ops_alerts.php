<?php
/**
 * Storage and quota warning messages for admin (DataDock 2.1+).
 */

/**
 * @return list<array{type:string,message:string,severity:string}>
 */
function datadock_collect_ops_alerts(PDO $pdo, array $settings): array {
    $alerts = [];
    $oa = $settings['ops_alerts'] ?? [];

    $storageEnabled = !empty($oa['storage_partition_percent_enabled']);
    $storagePct = (int) ($oa['storage_partition_percent_threshold'] ?? 85);
    if ($storagePct < 1) {
        $storagePct = 1;
    }
    if ($storagePct > 100) {
        $storagePct = 100;
    }

    if ($storageEnabled) {
        require_once __DIR__ . '/storage.php';
        $parts = $pdo->query('SELECT id, name FROM storage_partitions ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($parts as $p) {
            $pid = (int) $p['id'];
            $root = datadock_partition_root($pdo, $pid);
            if ($root === '' || !is_dir($root)) {
                continue;
            }
            $total = @disk_total_space($root);
            $free = @disk_free_space($root);
            if ($total === false || $free === false || $total <= 0) {
                continue;
            }
            $used = $total - $free;
            $ratio = $used / $total * 100;
            if ($ratio >= $storagePct) {
                $alerts[] = [
                    'type' => 'storage_partition',
                    'severity' => $ratio >= 95 ? 'danger' : 'warning',
                    'message' => 'Partition «' . ($p['name'] ?? (string) $pid) . '» disk is ' . round($ratio, 1)
                        . '% full (' . format_filesize((int) $free) . ' free of ' . format_filesize((int) $total) . ').',
                ];
            }
        }
    }

    $quotaEnabled = !empty($oa['user_quota_percent_enabled']);
    $quotaPct = (int) ($oa['user_quota_percent_threshold'] ?? 90);
    if ($quotaPct < 1) {
        $quotaPct = 1;
    }
    if ($quotaPct > 100) {
        $quotaPct = 100;
    }

    $ul = $settings['user_limits'] ?? [];
    $maxFilesOn = !empty($ul['max_files_enabled']);
    $maxFiles = (int) ($ul['max_files'] ?? 0);
    $maxStorageOn = !empty($ul['max_storage_enabled']);
    $maxStorage = (int) ($ul['max_storage'] ?? 0);

    if ($quotaEnabled && ($maxFilesOn || $maxStorageOn)) {
        $stmt = $pdo->query('
            SELECT u.id, u.username,
                COUNT(f.id) AS file_count,
                COALESCE(SUM(f.filesize), 0) AS total_size
            FROM users u
            LEFT JOIN files f ON u.id = f.user_id AND f.deleted_at IS NULL
            GROUP BY u.id, u.username
        ');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $uname = (string) ($row['username'] ?? '');
            if ($maxFilesOn && $maxFiles > 0) {
                $fc = (int) $row['file_count'];
                $r = $fc / $maxFiles * 100;
                if ($r >= $quotaPct) {
                    $alerts[] = [
                        'type' => 'quota_files',
                        'severity' => $r >= 100 ? 'danger' : 'warning',
                        'message' => 'User «' . $uname . '» is at ' . round($r, 1)
                            . '% of the max file count (' . $fc . ' / ' . $maxFiles . ').',
                    ];
                }
            }
            if ($maxStorageOn && $maxStorage > 0) {
                $ts = (int) $row['total_size'];
                $r = $ts / $maxStorage * 100;
                if ($r >= $quotaPct) {
                    $alerts[] = [
                        'type' => 'quota_storage',
                        'severity' => $r >= 100 ? 'danger' : 'warning',
                        'message' => 'User «' . $uname . '» is at ' . round($r, 1)
                            . '% of storage quota (' . format_filesize($ts) . ' / ' . format_filesize($maxStorage) . ').',
                    ];
                }
            }
        }
    }

    $guestOn = !empty($settings['guest_uploads']['enabled']);
    if ($quotaEnabled && $guestOn) {
        $gMaxF = (int) ($settings['guest_uploads']['max_files'] ?? 0);
        $gMaxS = (int) ($settings['guest_uploads']['max_storage'] ?? 0);
        $stmt = $pdo->query("
            SELECT guest_id, COUNT(*) AS c, COALESCE(SUM(filesize),0) AS s
            FROM files WHERE user_id IS NULL AND guest_id IS NOT NULL AND deleted_at IS NULL
            GROUP BY guest_id
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $gid = (string) ($row['guest_id'] ?? '');
            if ($gMaxF > 0) {
                $c = (int) $row['c'];
                $r = $c / $gMaxF * 100;
                if ($r >= $quotaPct) {
                    $alerts[] = [
                        'type' => 'guest_quota_files',
                        'severity' => $r >= 100 ? 'danger' : 'warning',
                        'message' => 'A guest session is at ' . round($r, 1)
                            . '% of guest file limit (' . $c . ' / ' . $gMaxF . '). ID: ' . substr($gid, 0, 8) . '…',
                    ];
                }
            }
            if ($gMaxS > 0) {
                $s = (int) $row['s'];
                $r = $s / $gMaxS * 100;
                if ($r >= $quotaPct) {
                    $alerts[] = [
                        'type' => 'guest_quota_storage',
                        'severity' => $r >= 100 ? 'danger' : 'warning',
                        'message' => 'A guest session is at ' . round($r, 1)
                            . '% of guest storage (' . format_filesize($s) . ' / ' . format_filesize($gMaxS) . ').',
                    ];
                }
            }
        }
    }

    return $alerts;
}
