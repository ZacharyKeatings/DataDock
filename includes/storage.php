<?php
/**
 * Storage partitions, deduplicated objects, and per-file disk paths (DataDock 2.x).
 */

/**
 * Absolute partition root (no trailing slash). Empty root_path uses site storage_base_path.
 */
function datadock_partition_root(PDO $pdo, int $partitionId): string {
    static $cache = [];
    if (isset($cache[$partitionId])) {
        return $cache[$partitionId];
    }
    $stmt = $pdo->prepare('SELECT root_path FROM storage_partitions WHERE id = ?');
    $stmt->execute([$partitionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $cfg = $row ? trim((string) ($row['root_path'] ?? '')) : '';
    if ($cfg === '') {
        $base = _resolve_storage_base();
    } else {
        if ($cfg[0] === '/' || (PHP_OS_FAMILY === 'Windows' && preg_match('#^[A-Za-z]:[/\\\\]#', $cfg))) {
            $base = rtrim(str_replace('\\', '/', $cfg), '/');
        } else {
            $base = rtrim(str_replace('\\', '/', dirname(__DIR__) . '/' . ltrim($cfg, '/')), '/');
        }
    }
    $cache[$partitionId] = $base;
    return $base;
}

function datadock_upload_dir(PDO $pdo, int $partitionId): string {
    return datadock_partition_root($pdo, $partitionId) . '/uploads/';
}

function datadock_thumbnails_dir(PDO $pdo, int $partitionId): string {
    return datadock_partition_root($pdo, $partitionId) . '/thumbnails/';
}

/**
 * Default partition id (is_default = 1), or first row, or 1.
 */
function datadock_get_default_partition_id(PDO $pdo): int {
    static $id = null;
    if ($id !== null) {
        return $id;
    }
    $stmt = $pdo->query('SELECT id FROM storage_partitions WHERE is_default = 1 ORDER BY id ASC LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $id = (int) $row['id'];
        return $id;
    }
    $stmt = $pdo->query('SELECT id FROM storage_partitions ORDER BY id ASC LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $id = $row ? (int) $row['id'] : 1;
    return $id;
}

/**
 * Resolve storage partition for a user. Guests use default.
 */
function datadock_resolve_user_partition_id(PDO $pdo, ?int $userId): int {
    if ($userId === null || $userId <= 0) {
        return datadock_get_default_partition_id($pdo);
    }
    $stmt = $pdo->prepare('SELECT storage_partition_id FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $v = $stmt->fetchColumn();
    if ($v !== false && $v !== null && (int) $v > 0) {
        return (int) $v;
    }
    return datadock_get_default_partition_id($pdo);
}

/**
 * Full path to main file on disk for a files row.
 */
function datadock_file_main_path(PDO $pdo, array $file): string {
    $partId = (int) ($file['storage_partition_id'] ?? 1);
    $uploadDir = datadock_upload_dir($pdo, $partId);
    if (!empty($file['storage_object_id'])) {
        $oid = (int) $file['storage_object_id'];
        static $objCache = [];
        if (!isset($objCache[$oid])) {
            $st = $pdo->prepare('SELECT stored_filename FROM storage_objects WHERE id = ?');
            $st->execute([$oid]);
            $objCache[$oid] = $st->fetchColumn();
        }
        $fn = $objCache[$oid];
        if (is_string($fn) && $fn !== '') {
            return $uploadDir . $fn;
        }
    }
    return $uploadDir . ($file['filename'] ?? '');
}

/**
 * Full path to thumbnail or null if none.
 */
function datadock_file_thumb_path(PDO $pdo, array $file): ?string {
    if (empty($file['thumbnail_path'])) {
        return null;
    }
    $partId = (int) ($file['storage_partition_id'] ?? 1);
    return datadock_thumbnails_dir($pdo, $partId) . $file['thumbnail_path'];
}

/**
 * Find existing deduplicated object in a partition by SHA-256.
 *
 * @return array{id:int, stored_filename:string, ref_count:int}|null
 */
function datadock_find_duplicate_object(PDO $pdo, int $partitionId, string $sha256): ?array {
    $sha256 = strtolower(trim($sha256));
    if (strlen($sha256) !== 64) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, stored_filename, ref_count FROM storage_objects WHERE storage_partition_id = ? AND sha256 = ?');
    $stmt->execute([$partitionId, $sha256]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Insert a new storage object and return its id.
 */
function datadock_create_storage_object(PDO $pdo, int $partitionId, string $sha256, string $storedFilename, int $byteSize): int {
    $sha256 = strtolower(trim($sha256));
    $stmt = $pdo->prepare('
        INSERT INTO storage_objects (storage_partition_id, sha256, stored_filename, byte_size, ref_count)
        VALUES (?, ?, ?, ?, 1)
    ');
    $stmt->execute([$partitionId, $sha256, $storedFilename, $byteSize]);
    return (int) $pdo->lastInsertId();
}

function datadock_increment_object_ref(PDO $pdo, int $objectId): void {
    $pdo->prepare('UPDATE storage_objects SET ref_count = ref_count + 1 WHERE id = ?')->execute([$objectId]);
}

/**
 * Permanently remove file bytes when a file row is deleted. Handles deduplicated objects.
 */
function datadock_release_file_storage(PDO $pdo, array $file): void {
    $thumbPath = datadock_file_thumb_path($pdo, $file);
    if ($thumbPath !== null && file_exists($thumbPath)) {
        @unlink($thumbPath);
    }

    $objectId = isset($file['storage_object_id']) ? (int) $file['storage_object_id'] : 0;
    if ($objectId > 0) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE storage_objects SET ref_count = ref_count - 1 WHERE id = ?')->execute([$objectId]);
            $stmt = $pdo->prepare('SELECT ref_count, stored_filename, storage_partition_id FROM storage_objects WHERE id = ?');
            $stmt->execute([$objectId]);
            $obj = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($obj && (int) $obj['ref_count'] <= 0) {
                $partId = (int) $obj['storage_partition_id'];
                $main = datadock_upload_dir($pdo, $partId) . $obj['stored_filename'];
                if (file_exists($main)) {
                    @unlink($main);
                }
                $pdo->prepare('DELETE FROM storage_objects WHERE id = ?')->execute([$objectId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return;
    }

    $mainPath = datadock_file_main_path($pdo, $file);
    if (file_exists($mainPath)) {
        @unlink($mainPath);
    }
}

/**
 * Ensure uploads/ and thumbnails/ exist for a partition.
 */
function datadock_ensure_partition_dirs(PDO $pdo, int $partitionId): void {
    $u = datadock_upload_dir($pdo, $partitionId);
    $t = datadock_thumbnails_dir($pdo, $partitionId);
    if (!is_dir($u)) {
        @mkdir($u, 0755, true);
    }
    if (!is_dir($t)) {
        @mkdir($t, 0755, true);
    }
}

/**
 * Remove all files in uploads/ and thumbnails/ for every partition (admin reset).
 */
/**
 * Replace tag associations for a file from a comma-separated list.
 */
function datadock_sync_file_tags(PDO $pdo, int $fileId, int $userId, string $tagsCsv): void {
    $pdo->prepare('DELETE FROM file_tags WHERE file_id = ?')->execute([$fileId]);
    $parts = preg_split('/\s*,\s*/', $tagsCsv, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($parts as $name) {
        $name = trim($name);
        if ($name === '' || strlen($name) > 100) {
            continue;
        }
        $stmt = $pdo->prepare('SELECT id FROM tags WHERE user_id = ? AND name = ?');
        $stmt->execute([$userId, $name]);
        $tid = $stmt->fetchColumn();
        if (!$tid) {
            $pdo->prepare('INSERT INTO tags (user_id, name) VALUES (?, ?)')->execute([$userId, $name]);
            $tid = (int) $pdo->lastInsertId();
        } else {
            $tid = (int) $tid;
        }
        $pdo->prepare('INSERT IGNORE INTO file_tags (file_id, tag_id) VALUES (?, ?)')->execute([$fileId, $tid]);
    }
}

function datadock_clear_all_partition_files_on_disk(PDO $pdo): void {
    $ids = $pdo->query('SELECT id FROM storage_partitions')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $pid) {
        $pid = (int) $pid;
        $clear = static function (string $path): void {
            if (!is_dir($path)) {
                return;
            }
            foreach (glob($path . '*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        };
        $clear(datadock_upload_dir($pdo, $pid));
        $clear(datadock_thumbnails_dir($pdo, $pid));
    }
}
