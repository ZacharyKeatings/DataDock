<?php
/**
 * Shared upload ingest: MIME checks, optional scan, dedup, quota, and the files row.
 */

require_once __DIR__ . '/upload_scan.php';
require_once __DIR__ . '/quotas.php';

/**
 * Write a small JPEG thumbnail. Missing image support, or a picture too large to decode, skips the thumb instead of failing the upload.
 */
function datadock_write_image_thumbnail(string $sourcePath, string $thumbPath): bool {
    if (!function_exists('imagecreatefromstring') || !function_exists('imagescale') || !function_exists('imagejpeg')) {
        return false;
    }
    $size = @filesize($sourcePath);
    if ($size === false || $size < 1 || $size > 33554432 || !is_readable($sourcePath)) {
        return false;
    }
    try {
        $bytes = file_get_contents($sourcePath);
        if (!is_string($bytes) || $bytes === '') {
            return false;
        }
        $img = @imagecreatefromstring($bytes);
        if ($img === false) {
            return false;
        }
        $thumb = @imagescale($img, 100);
        imagedestroy($img);
        if ($thumb === false) {
            return false;
        }
        $ok = @imagejpeg($thumb, $thumbPath);
        imagedestroy($thumb);
        return $ok;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * @param array{source_path:string,original_name:string,file_size?:int,user_id?:?int,guest_id?:?string,folder_id?:?int,is_public?:int,expiry_ts?:?string,relocate?:string} $spec
 * relocate is "upload" (move_uploaded_file) or "rename".
 * @return array{ok:bool,error:?string,file_id:?int}
 */
function datadock_ingest_local_file(PDO $pdo, array $settings, array $spec): array {
    $source = (string) ($spec['source_path'] ?? '');
    $originalName = (string) ($spec['original_name'] ?? 'upload');
    $originalName = str_replace(["\0", '/', '\\'], '', $originalName);
    if ($originalName === '') {
        $originalName = 'upload';
    }
    if (strlen($originalName) > 255) {
        $originalName = substr($originalName, 0, 255);
    }
    if ($source === '' || !is_readable($source)) {
        return ['ok' => false, 'error' => "❌ {$originalName} could not be read.", 'file_id' => null];
    }
    $fileSize = isset($spec['file_size']) ? (int) $spec['file_size'] : (int) filesize($source);
    $userId = isset($spec['user_id']) ? (int) $spec['user_id'] : 0;
    $guestId = isset($spec['guest_id']) ? (string) $spec['guest_id'] : '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $forbidden = get_forbidden_upload_extensions();
    if (in_array($extension, $forbidden, true) || upload_basename_has_dangerous_extension_segment($originalName)) {
        return ['ok' => false, 'error' => "❌ {$originalName} has a forbidden file type.", 'file_id' => null];
    }
    if (!datadock_extension_allowed_by_policy($extension, $settings)) {
        return ['ok' => false, 'error' => "❌ {$originalName} is not an allowed file type.", 'file_id' => null];
    }
    $appMax = (int) ($settings['max_file_size'] ?? 0);
    if ($appMax > 0 && $fileSize > $appMax) {
        return ['ok' => false, 'error' => "❌ {$originalName} is too large. Max is " . format_filesize($appMax) . '.', 'file_id' => null];
    }
    if ($userId > 0) {
        $quotaError = datadock_user_quota_error($pdo, $userId, $settings, 1, $fileSize);
        if ($quotaError !== null) {
            return ['ok' => false, 'error' => '❌ ' . $quotaError, 'file_id' => null];
        }
    }
    $clientEncrypted = !empty($spec['client_encrypted']);
    if (!function_exists('datadock_file_is_sealed')) {
        require_once __DIR__ . '/personal.php';
    }
    if ($clientEncrypted && !datadock_file_is_sealed($source)) {
        return ['ok' => false, 'error' => "❌ {$originalName} was not sealed in the browser.", 'file_id' => null];
    }
    $mimeType = mime_content_type($source);
    if (!is_string($mimeType) || $mimeType === '') {
        $mimeType = 'application/octet-stream';
    }
    if ($clientEncrypted) {
        $mimeType = 'application/octet-stream';
    } else {
        if (!upload_strict_image_format_ok($source, $extension, $mimeType)) {
            return ['ok' => false, 'error' => "❌ {$originalName}: file content does not match the image type implied by the extension.", 'file_id' => null];
        }
        if (upload_image_body_has_embedded_script_markers($source, $mimeType, $extension)) {
            return ['ok' => false, 'error' => "❌ {$originalName}: blocked (executable/script content inside a binary image).", 'file_id' => null];
        }
    }

    $checksumMd5 = hash_file('md5', $source);
    $checksumSha = hash_file('sha256', $source);
    $partitionId = datadock_resolve_user_partition_id($pdo, $userId > 0 ? $userId : null);
    datadock_ensure_partition_dirs($pdo, $partitionId);
    $uploadDir = datadock_upload_dir($pdo, $partitionId);
    $thumbDir = datadock_thumbnails_dir($pdo, $partitionId);
    $rewrite = !empty($settings['rewrite_file_extension']);
    $dedupEnabled = !isset($settings['deduplicate_storage']) || !empty($settings['deduplicate_storage']);
    $dupRow = ($dedupEnabled && $checksumSha !== '') ? datadock_find_duplicate_object($pdo, $partitionId, $checksumSha) : null;

    if ($dupRow !== null) {
        datadock_increment_object_ref($pdo, (int) $dupRow['id']);
        $storageObjectId = (int) $dupRow['id'];
        $uniqueName = (string) $dupRow['stored_filename'];
        $destPath = $uploadDir . $uniqueName;
        @unlink($source);
    } else {
        $uniqueName = $rewrite ? uniqid('', true) : (uniqid('', true) . ($extension !== '' ? ".$extension" : ''));
        $destPath = $uploadDir . $uniqueName;
        $relocate = ($spec['relocate'] ?? 'rename') === 'upload' ? 'upload' : 'rename';
        $moved = $relocate === 'upload' ? @move_uploaded_file($source, $destPath) : @rename($source, $destPath);
        if (!$moved) {
            if (!@copy($source, $destPath)) {
                return ['ok' => false, 'error' => "❌ Failed to store {$originalName}.", 'file_id' => null];
            }
            @unlink($source);
        }
        $storageObjectId = datadock_create_storage_object($pdo, $partitionId, $checksumSha, $uniqueName, $fileSize);
    }

    $scan = datadock_upload_scan_file($destPath, $settings);
    $quarantineOn = !empty($settings['upload_quarantine_enabled']);
    $quarantineStatus = ($quarantineOn || !$scan['passed']) ? 'pending' : 'approved';
    $mimeAnomaly = (int) mime_extension_mismatch($extension, $mimeType);

    $thumbnailName = null;
    $thumbnailsEnabled = !isset($settings['thumbnails_enabled']) || !empty($settings['thumbnails_enabled']);
    if ($thumbnailsEnabled && str_starts_with($mimeType, 'image/')) {
        $thumbBase = pathinfo($uniqueName, PATHINFO_FILENAME) ?: $uniqueName;
        $thumbnailName = 'thumb_' . $thumbBase . '.jpg';
        $thumbPath = $thumbDir . $thumbnailName;
        if (!file_exists($thumbPath) && !datadock_write_image_thumbnail($destPath, $thumbPath)) {
            $thumbnailName = null;
        }
    }

    $folderId = isset($spec['folder_id']) ? (int) $spec['folder_id'] : 0;
    if ($userId < 1 || $folderId < 1) {
        $folderId = 0;
    } else {
        $chk = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ?');
        $chk->execute([$folderId, $userId]);
        if (!$chk->fetch()) {
            $folderId = 0;
        }
    }

    $nowUTC = new DateTime('now', new DateTimeZone('UTC'));
    $uploadTs = $nowUTC->format('Y-m-d H:i:s');
    $expiryTs = $spec['expiry_ts'] ?? null;
    $isPublic = !empty($spec['is_public']) ? 1 : 0;

    $ins = $pdo->prepare("
        INSERT INTO files
          (user_id, guest_id, storage_partition_id, folder_id, is_public, filename, storage_object_id, original_name,
           filetype, filesize, thumbnail_path, upload_date, expiry_date, checksum_md5, checksum_sha256,
           quarantine_status, mime_anomaly, client_encrypted)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([
        $userId > 0 ? $userId : null,
        ($userId > 0 || $guestId === '') ? null : $guestId,
        $partitionId,
        $folderId > 0 ? $folderId : null,
        $isPublic,
        $uniqueName,
        $storageObjectId,
        $originalName,
        $mimeType,
        $fileSize,
        $thumbnailName,
        $uploadTs,
        $expiryTs,
        $checksumMd5,
        $checksumSha,
        $quarantineStatus,
        $mimeAnomaly,
        $clientEncrypted ? 1 : 0,
    ]);
    $newFileId = (int) $pdo->lastInsertId();
    if ($userId > 0 && $checksumSha !== '') {
        datadock_notice_duplicate_file($pdo, $userId, $checksumSha, $newFileId, $originalName);
    }
    return ['ok' => true, 'error' => null, 'file_id' => $newFileId, 'quarantine' => $quarantineStatus, 'scan' => $scan['message']];
}

/**
 * Replace the bytes of an existing file. Shares and links stay on the same id.
 * The previous bytes are kept as a version until trash retention removes them.
 *
 * @return array{ok:bool,error:?string}
 */
function datadock_replace_file_contents(PDO $pdo, array $settings, array $file, string $sourcePath, string $originalName, bool $clientEncrypted = false): array {
    $fileId = (int) ($file['id'] ?? 0);
    if ($fileId < 1 || !is_readable($sourcePath)) {
        return ['ok' => false, 'error' => 'The replacement file could not be read.'];
    }
    $originalName = str_replace(["\0", '/', '\\'], '', $originalName);
    if ($originalName === '') {
        $originalName = (string) ($file['original_name'] ?? 'upload');
    }
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (in_array($extension, get_forbidden_upload_extensions(), true) || upload_basename_has_dangerous_extension_segment($originalName)) {
        return ['ok' => false, 'error' => 'That file type is not allowed.'];
    }
    if (!datadock_extension_allowed_by_policy($extension, $settings)) {
        return ['ok' => false, 'error' => 'That file type is not allowed.'];
    }
    $fileSize = (int) filesize($sourcePath);
    $appMax = (int) ($settings['max_file_size'] ?? 0);
    if ($appMax > 0 && $fileSize > $appMax) {
        return ['ok' => false, 'error' => 'That file is too large. Max is ' . format_filesize($appMax) . '.'];
    }
    $ownerId = (int) ($file['user_id'] ?? 0);
    if ($ownerId > 0) {
        $oldSize = (int) ($file['filesize'] ?? 0);
        $delta = max(0, $fileSize - $oldSize);
        $quotaError = datadock_user_quota_error($pdo, $ownerId, $settings, 0, $delta);
        if ($quotaError !== null) {
            return ['ok' => false, 'error' => $quotaError];
        }
    }
    if (!function_exists('datadock_file_is_sealed')) {
        require_once __DIR__ . '/personal.php';
    }
    if ($clientEncrypted && !datadock_file_is_sealed($sourcePath)) {
        return ['ok' => false, 'error' => 'That file was not sealed in the browser.'];
    }
    $mimeType = mime_content_type($sourcePath);
    if (!is_string($mimeType) || $mimeType === '') {
        $mimeType = 'application/octet-stream';
    }
    if ($clientEncrypted) {
        $mimeType = 'application/octet-stream';
    } else {
        if (!upload_strict_image_format_ok($sourcePath, $extension, $mimeType)) {
            return ['ok' => false, 'error' => 'File content does not match the image type implied by the extension.'];
        }
        if (upload_image_body_has_embedded_script_markers($sourcePath, $mimeType, $extension)) {
            return ['ok' => false, 'error' => 'Blocked (executable/script content inside a binary image).'];
        }
    }

    $checksumMd5 = hash_file('md5', $sourcePath);
    $checksumSha = hash_file('sha256', $sourcePath);
    $partitionId = (int) ($file['storage_partition_id'] ?? 0);
    if ($partitionId < 1) {
        $partitionId = datadock_resolve_user_partition_id($pdo, $ownerId > 0 ? $ownerId : null);
    }
    datadock_ensure_partition_dirs($pdo, $partitionId);
    $uploadDir = datadock_upload_dir($pdo, $partitionId);
    $oldSha = strtolower((string) ($file['checksum_sha256'] ?? ''));
    if ($oldSha !== '' && hash_equals($oldSha, $checksumSha)) {
        @unlink($sourcePath);
        $pdo->prepare('UPDATE files SET original_name = ?, filetype = ? WHERE id = ?')->execute([$originalName, $mimeType, $fileId]);
        return ['ok' => true, 'error' => null];
    }

    $rewrite = !empty($settings['rewrite_file_extension']);
    $dedupEnabled = !isset($settings['deduplicate_storage']) || !empty($settings['deduplicate_storage']);
    $dupRow = ($dedupEnabled && $checksumSha !== '') ? datadock_find_duplicate_object($pdo, $partitionId, $checksumSha) : null;
    $oldObjectId = (int) ($file['storage_object_id'] ?? 0);
    if ($dupRow !== null && (int) $dupRow['id'] === $oldObjectId) {
        @unlink($sourcePath);
        return ['ok' => true, 'error' => null];
    }
    if ($dupRow !== null) {
        datadock_increment_object_ref($pdo, (int) $dupRow['id']);
        $newObjectId = (int) $dupRow['id'];
        $uniqueName = (string) $dupRow['stored_filename'];
        $destPath = $uploadDir . $uniqueName;
        @unlink($sourcePath);
    } else {
        $uniqueName = $rewrite ? uniqid('', true) : (uniqid('', true) . ($extension !== '' ? ".$extension" : ''));
        $destPath = $uploadDir . $uniqueName;
        if (!@rename($sourcePath, $destPath)) {
            if (!@copy($sourcePath, $destPath)) {
                return ['ok' => false, 'error' => 'Failed to store the replacement.'];
            }
            @unlink($sourcePath);
        }
        $newObjectId = datadock_create_storage_object($pdo, $partitionId, $checksumSha, $uniqueName, $fileSize);
    }

    $scan = datadock_upload_scan_file($destPath, $settings);
    $quarantineOn = !empty($settings['upload_quarantine_enabled']);
    $quarantineStatus = ($quarantineOn || !$scan['passed']) ? 'pending' : 'approved';

    $thumbnailName = null;
    $thumbnailsEnabled = !isset($settings['thumbnails_enabled']) || !empty($settings['thumbnails_enabled']);
    if (!$clientEncrypted && $thumbnailsEnabled && str_starts_with($mimeType, 'image/')) {
        $thumbBase = pathinfo($uniqueName, PATHINFO_FILENAME) ?: $uniqueName;
        $thumbnailName = 'thumb_' . $thumbBase . '.jpg';
        $thumbPath = datadock_thumbnails_dir($pdo, $partitionId) . $thumbnailName;
        if (!file_exists($thumbPath) && !datadock_write_image_thumbnail($destPath, $thumbPath)) {
            $thumbnailName = null;
        }
    }

    if ($oldObjectId > 0) {
        datadock_increment_object_ref($pdo, $oldObjectId);
    }
    $pdo->prepare("
        INSERT INTO files
          (user_id, guest_id, storage_partition_id, folder_id, is_public, filename, storage_object_id, original_name,
           filetype, filesize, thumbnail_path, upload_date, expiry_date, checksum_md5, checksum_sha256,
           quarantine_status, mime_anomaly, deleted_at, purge_hold, download_count, client_encrypted, replaced_file_id)
        SELECT user_id, guest_id, storage_partition_id, folder_id, is_public, filename, storage_object_id, original_name,
               filetype, filesize, thumbnail_path, upload_date, expiry_date, checksum_md5, checksum_sha256,
               quarantine_status, mime_anomaly, UTC_TIMESTAMP(), 0, 0, client_encrypted, ?
        FROM files WHERE id = ?
    ")->execute([$fileId, $fileId]);
    if (!function_exists('datadock_trim_file_versions')) {
        require_once __DIR__ . '/personal.php';
    }
    datadock_trim_file_versions($pdo, $fileId, 5);

    $pdo->prepare('
        UPDATE files
           SET filename = ?, storage_object_id = ?, original_name = ?, filetype = ?, filesize = ?,
               thumbnail_path = ?, checksum_md5 = ?, checksum_sha256 = ?, quarantine_status = ?, mime_anomaly = ?,
               client_encrypted = ?
         WHERE id = ?
    ')->execute([
        $uniqueName,
        $newObjectId,
        $originalName,
        $mimeType,
        $fileSize,
        $thumbnailName,
        $checksumMd5,
        $checksumSha,
        $quarantineStatus,
        $clientEncrypted ? 0 : (int) mime_extension_mismatch($extension, $mimeType),
        $clientEncrypted ? 1 : 0,
        $fileId,
    ]);

    if ($oldObjectId > 0) {
        $pdo->prepare('UPDATE storage_objects SET ref_count = ref_count - 1 WHERE id = ?')->execute([$oldObjectId]);
    }
    return ['ok' => true, 'error' => null];
}
