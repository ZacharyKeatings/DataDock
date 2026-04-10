<?php
/**
 * Disk vs DB integrity and checksum verification (DataDock 2.1+).
 */
require_once __DIR__ . '/storage.php';

/**
 * Expected blob names on disk per partition (dedup objects + non-dedup filenames).
 *
 * @return array<int, array<string,true>>
 */
function datadock_expected_upload_filenames_by_partition(PDO $pdo): array {
    $byPart = [];
    $stmt = $pdo->query('SELECT DISTINCT storage_partition_id, stored_filename FROM storage_objects');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int) $row['storage_partition_id'];
        $fn = (string) ($row['stored_filename'] ?? '');
        if ($fn !== '') {
            $byPart[$pid][$fn] = true;
        }
    }
    $stmt = $pdo->query('
        SELECT storage_partition_id, filename FROM files
        WHERE (storage_object_id IS NULL OR storage_object_id = 0) AND filename IS NOT NULL AND filename != \'\'
    ');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int) $row['storage_partition_id'];
        $fn = (string) ($row['filename'] ?? '');
        if ($fn !== '') {
            $byPart[$pid][$fn] = true;
        }
    }
    return $byPart;
}

/**
 * @return array{missing:list<array{file_id:int,partition_id:int,name:string}>,orphaned:list<array{partition_id:int,path:string,name:string}>}
 */
function datadock_scan_uploads_vs_database(PDO $pdo): array {
    $missing = [];
    $orphaned = [];

    $stmt = $pdo->query('SELECT id, storage_partition_id, original_name, filename, storage_object_id FROM files');
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($files as $file) {
        $path = datadock_file_main_path($pdo, $file);
        if (!is_file($path)) {
            $missing[] = [
                'file_id' => (int) $file['id'],
                'partition_id' => (int) ($file['storage_partition_id'] ?? 1),
                'name' => (string) ($file['original_name'] ?? $file['filename'] ?? '?'),
            ];
        }
    }

    $expectedByPart = datadock_expected_upload_filenames_by_partition($pdo);
    $partIds = $pdo->query('SELECT id FROM storage_partitions ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($partIds as $pid) {
        $pid = (int) $pid;
        $uploadDir = datadock_upload_dir($pdo, $pid);
        if (!is_dir($uploadDir)) {
            continue;
        }
        $expected = $expectedByPart[$pid] ?? [];
        foreach (glob($uploadDir . '*') ?: [] as $full) {
            if (!is_file($full)) {
                continue;
            }
            $base = basename($full);
            if ($base === '' || $base === '.htaccess') {
                continue;
            }
            if (empty($expected[$base])) {
                $orphaned[] = [
                    'partition_id' => $pid,
                    'path' => $full,
                    'name' => $base,
                ];
            }
        }
    }

    return ['missing' => $missing, 'orphaned' => $orphaned];
}

/**
 * @return array{checked:int,ok:int,mismatch:list<array{file_id:int,name:string,expected_md5:?string,actual_md5:?string,expected_sha256:?string,actual_sha256:?string}>,errors:list<string>}
 */
function datadock_verify_file_checksums(PDO $pdo, int $limit = 0): array {
    $checked = 0;
    $ok = 0;
    $mismatch = [];
    $errors = [];

    $sql = 'SELECT id, original_name, filename, storage_object_id, checksum_md5, checksum_sha256 FROM files WHERE deleted_at IS NULL';
    if ($limit > 0) {
        $sql .= ' ORDER BY id DESC LIMIT ' . (int) $limit;
    }
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $file) {
        $path = datadock_file_main_path($pdo, $file);
        if (!is_readable($path)) {
            $errors[] = 'File id ' . (int) $file['id'] . ': not readable on disk.';
            continue;
        }
        $expMd5 = $file['checksum_md5'] ?? null;
        $expSha = $file['checksum_sha256'] ?? null;
        if (($expMd5 === null || $expMd5 === '') && ($expSha === null || $expSha === '')) {
            continue;
        }
        $checked++;
        $actMd5 = null;
        $actSha = null;
        if ($expMd5 !== null && $expMd5 !== '') {
            $actMd5 = hash_file('md5', $path);
        }
        if ($expSha !== null && $expSha !== '') {
            $actSha = hash_file('sha256', $path);
        }
        $bad = false;
        if ($expMd5 !== null && $expMd5 !== '' && strcasecmp((string) $expMd5, (string) $actMd5) !== 0) {
            $bad = true;
        }
        if ($expSha !== null && $expSha !== '' && strcasecmp((string) $expSha, (string) $actSha) !== 0) {
            $bad = true;
        }
        if ($bad) {
            $mismatch[] = [
                'file_id' => (int) $file['id'],
                'name' => (string) ($file['original_name'] ?? $file['filename'] ?? '?'),
                'expected_md5' => $expMd5,
                'actual_md5' => $actMd5,
                'expected_sha256' => $expSha,
                'actual_sha256' => $actSha,
            ];
        } else {
            $ok++;
        }
    }

    return ['checked' => $checked, 'ok' => $ok, 'mismatch' => $mismatch, 'errors' => $errors];
}

/**
 * Recompute and store MD5/SHA256 from disk for active files (repair).
 *
 * @return array{updated:int,skipped:int,errors:list<string>}
 */
function datadock_rehash_files_from_disk(PDO $pdo, int $limit = 0): array {
    $updated = 0;
    $skipped = 0;
    $errors = [];

    $sql = 'SELECT id FROM files WHERE deleted_at IS NULL';
    if ($limit > 0) {
        $sql .= ' ORDER BY id DESC LIMIT ' . (int) $limit;
    }
    $stmt = $pdo->query($sql);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $sel = $pdo->prepare('SELECT * FROM files WHERE id = ?');
    $upd = $pdo->prepare('UPDATE files SET checksum_md5 = ?, checksum_sha256 = ? WHERE id = ?');

    foreach ($ids as $fid) {
        $sel->execute([(int) $fid]);
        $file = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$file) {
            continue;
        }
        $path = datadock_file_main_path($pdo, $file);
        if (!is_readable($path)) {
            $errors[] = 'id ' . (int) $fid . ': not readable';
            $skipped++;
            continue;
        }
        $md5 = hash_file('md5', $path);
        $sha = hash_file('sha256', $path);
        $upd->execute([$md5, $sha, (int) $fid]);
        $updated++;
    }

    return ['updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
}
