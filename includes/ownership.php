<?php
/**
 * Move a file onto another account, copying bytes when the storage partition changes.
 */

function datadock_move_file_to_user(PDO $pdo, array $file, int $targetUserId, int $targetPartitionId): void {
    $fileId = (int) ($file['id'] ?? 0);
    if ($fileId < 1 || $targetUserId < 1) {
        return;
    }
    $sourcePartition = (int) ($file['storage_partition_id'] ?? 0);
    if ($sourcePartition < 1) {
        $sourcePartition = datadock_resolve_user_partition_id($pdo, isset($file['user_id']) ? (int) $file['user_id'] : null);
    }
    $folderId = isset($file['folder_id']) ? (int) $file['folder_id'] : 0;
    if ($folderId > 0) {
        $chk = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ?');
        $chk->execute([$folderId, $targetUserId]);
        if (!$chk->fetch()) {
            $folderId = 0;
        }
    }
    if ($sourcePartition === $targetPartitionId) {
        $pdo->prepare('UPDATE files SET user_id = ?, guest_id = NULL, folder_id = ? WHERE id = ?')->execute([
            $targetUserId,
            $folderId > 0 ? $folderId : null,
            $fileId,
        ]);
        return;
    }

    $src = datadock_file_main_path($pdo, $file);
    if (!is_readable($src)) {
        throw new RuntimeException('File bytes are missing.');
    }
    datadock_ensure_partition_dirs($pdo, $targetPartitionId);
    $sha = strtolower((string) ($file['checksum_sha256'] ?? ''));
    if (strlen($sha) !== 64) {
        $sha = hash_file('sha256', $src);
    }
    $size = (int) ($file['filesize'] ?? filesize($src));
    $dedup = datadock_find_duplicate_object($pdo, $targetPartitionId, $sha);
    if ($dedup) {
        datadock_increment_object_ref($pdo, (int) $dedup['id']);
        $objectId = (int) $dedup['id'];
        $stored = (string) $dedup['stored_filename'];
    } else {
        $ext = strtolower(pathinfo((string) ($file['original_name'] ?? $file['filename'] ?? ''), PATHINFO_EXTENSION));
        $stored = uniqid('', true) . ($ext !== '' ? ('.' . $ext) : '');
        $dest = datadock_upload_dir($pdo, $targetPartitionId) . $stored;
        if (!@copy($src, $dest)) {
            throw new RuntimeException('Could not copy the file onto the destination partition.');
        }
        $objectId = datadock_create_storage_object($pdo, $targetPartitionId, $sha, $stored, $size);
    }

    $thumbName = (string) ($file['thumbnail_path'] ?? '');
    if ($thumbName !== '') {
        $oldThumb = datadock_file_thumb_path($pdo, $file);
        $newThumb = datadock_thumbnails_dir($pdo, $targetPartitionId) . $thumbName;
        if ($oldThumb && is_readable($oldThumb) && !file_exists($newThumb)) {
            @copy($oldThumb, $newThumb);
        }
    }

    $pdo->prepare('UPDATE files SET user_id = ?, guest_id = NULL, folder_id = ?, storage_partition_id = ?, filename = ?, storage_object_id = ? WHERE id = ?')->execute([
        $targetUserId,
        $folderId > 0 ? $folderId : null,
        $targetPartitionId,
        $stored,
        $objectId,
        $fileId,
    ]);
    datadock_release_file_storage($pdo, $file);
}

/**
 * @return array{files:int,error:?string}
 */
function datadock_transfer_user_files(PDO $pdo, int $sourceUserId, int $targetUserId): array {
    if ($sourceUserId < 1 || $targetUserId < 1 || $sourceUserId === $targetUserId) {
        return ['files' => 0, 'error' => 'Choose two different accounts.'];
    }
    $users = $pdo->prepare('SELECT id, username, suspended FROM users WHERE id IN (?, ?)');
    $users->execute([$sourceUserId, $targetUserId]);
    $found = [];
    foreach ($users->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $found[(int) $row['id']] = $row;
    }
    if (!isset($found[$sourceUserId], $found[$targetUserId])) {
        return ['files' => 0, 'error' => 'One of those accounts was not found.'];
    }
    if (!empty($found[$targetUserId]['suspended'])) {
        return ['files' => 0, 'error' => 'The destination account is suspended.'];
    }
    $targetPartition = datadock_resolve_user_partition_id($pdo, $targetUserId);
    $stmt = $pdo->prepare('SELECT * FROM files WHERE user_id = ?');
    $stmt->execute([$sourceUserId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $moved = 0;
    foreach ($rows as $row) {
        datadock_move_file_to_user($pdo, $row, $targetUserId, $targetPartition);
        $moved++;
    }
    $pdo->prepare('UPDATE pastes SET user_id = ? WHERE user_id = ?')->execute([$targetUserId, $sourceUserId]);
    return ['files' => $moved, 'error' => null];
}
