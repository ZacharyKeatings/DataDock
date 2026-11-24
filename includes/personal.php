<?php
/**
 * Personal export, drop links, file versions, owner defaults, and the notice feed.
 */

require_once __DIR__ . '/notices.php';
require_once __DIR__ . '/storage.php';

/**
 * @return array<string, string|null>
 */
function datadock_expiry_offsets(): array {
    return [
        '1_minute' => '+1 minute',
        '30_minutes' => '+30 minutes',
        '1_hour' => '+1 hour',
        '6_hours' => '+6 hours',
        '1_day' => '+1 day',
        '1_week' => '+1 week',
        '1_month' => '+1 month',
        '1_year' => '+1 year',
        'never' => null,
    ];
}

function datadock_known_expiry_key(string $key): bool {
    return array_key_exists($key, datadock_expiry_offsets());
}

function datadock_share_preset(string $value): string {
    return in_array($value, ['onetime', 'password', 'signed'], true) ? $value : '';
}

function datadock_expiry_timestamp(?string $key): ?string {
    if ($key === null || !datadock_known_expiry_key($key)) {
        return null;
    }
    $offset = datadock_expiry_offsets()[$key];
    if ($offset === null) {
        return null;
    }
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $now->modify($offset);
    return $now->format('Y-m-d H:i:s');
}

/**
 * Folder expiry wins, then the owner's default, then the site default.
 * Public is set only when the folder says so.
 *
 * @return array{expiry:string,public:?int}
 */
function datadock_effective_upload_defaults(PDO $pdo, int $userId, int $folderId, string $siteExpiry): array {
    $expiry = datadock_known_expiry_key($siteExpiry) ? $siteExpiry : 'never';
    $public = null;
    if ($userId > 0) {
        try {
            $stmt = $pdo->prepare('SELECT default_expiry FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $userKey = (string) ($stmt->fetchColumn() ?: '');
            if (datadock_known_expiry_key($userKey)) {
                $expiry = $userKey;
            }
        } catch (Throwable $e) {
        }
    }
    if ($userId > 0 && $folderId > 0) {
        try {
            $stmt = $pdo->prepare('SELECT default_expiry, default_public FROM folders WHERE id = ? AND user_id = ?');
            $stmt->execute([$folderId, $userId]);
            $folder = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($folder)) {
                $folderKey = (string) ($folder['default_expiry'] ?? '');
                if (datadock_known_expiry_key($folderKey)) {
                    $expiry = $folderKey;
                }
                if ($folder['default_public'] !== null && $folder['default_public'] !== '') {
                    $public = (int) $folder['default_public'] === 1 ? 1 : 0;
                }
            }
        } catch (Throwable $e) {
        }
    }
    return ['expiry' => $expiry, 'public' => $public];
}

function datadock_folder_path(PDO $pdo, int $folderId, int $userId): string {
    $names = [];
    $guard = 0;
    while ($folderId > 0 && $guard < 40) {
        $guard++;
        $stmt = $pdo->prepare('SELECT parent_id, name FROM folders WHERE id = ? AND user_id = ?');
        $stmt->execute([$folderId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            break;
        }
        array_unshift($names, (string) $row['name']);
        $folderId = (int) $row['parent_id'];
    }
    return implode('/', $names);
}

function datadock_zip_entry_name(int $id, string $original): string {
    $base = str_replace(["\0", '/', '\\'], '', $original);
    $base = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $base) ?? 'file';
    $base = trim($base, '. ');
    if ($base === '') {
        $base = 'file';
    }
    if (strlen($base) > 80) {
        $base = substr($base, 0, 80);
    }
    return $id . '-' . $base;
}

/**
 * @return array{ok:bool,error:?string,count:int}
 */
function datadock_build_user_export_zip(PDO $pdo, int $userId, string $destPath): array {
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'Zip support is not installed.', 'count' => 0];
    }
    $stmt = $pdo->prepare('
        SELECT id, original_name, filename, folder_id, filesize, expiry_date, upload_date,
               checksum_md5, checksum_sha256, is_public, client_encrypted, storage_partition_id, storage_object_id, thumbnail_path
        FROM files
        WHERE user_id = ? AND deleted_at IS NULL AND replaced_file_id IS NULL
        ORDER BY id ASC
    ');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $zip = new ZipArchive();
    if ($zip->open($destPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return ['ok' => false, 'error' => 'Could not create the export archive.', 'count' => 0];
    }
    $manifest = [];
    $count = 0;
    foreach ($rows as $row) {
        $path = datadock_file_main_path($pdo, $row);
        $entry = 'files/' . datadock_zip_entry_name((int) $row['id'], (string) ($row['original_name'] ?? 'file'));
        $item = [
            'id' => (int) $row['id'],
            'name' => (string) ($row['original_name'] ?? ''),
            'sha256' => (string) ($row['checksum_sha256'] ?? ''),
            'md5' => (string) ($row['checksum_md5'] ?? ''),
            'expiry' => $row['expiry_date'] !== null ? (string) $row['expiry_date'] : null,
            'folder' => datadock_folder_path($pdo, (int) ($row['folder_id'] ?? 0), $userId),
            'size' => (int) ($row['filesize'] ?? 0),
            'public' => !empty($row['is_public']),
            'uploaded' => (string) ($row['upload_date'] ?? ''),
            'client_encrypted' => !empty($row['client_encrypted']),
            'path' => is_file($path) ? $entry : null,
        ];
        if (is_file($path)) {
            $zip->addFile($path, $entry);
            $count++;
        }
        $manifest[] = $item;
    }
    $document = [
        'export_version' => 1,
        'generated_at_utc' => gmdate('c'),
        'files' => $manifest,
    ];
    $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $zip->addFromString('manifest.json', $json === false ? '{}' : $json);
    $zip->close();
    return ['ok' => true, 'error' => null, 'count' => $count];
}

function datadock_notice_duplicate_file(PDO $pdo, int $userId, string $sha256, int $newFileId, string $name): void {
    $sha256 = strtolower(trim($sha256));
    if ($userId < 1 || $newFileId < 1 || strlen($sha256) !== 64) {
        return;
    }
    try {
        $stmt = $pdo->prepare('
            SELECT id, original_name FROM files
            WHERE user_id = ? AND checksum_sha256 = ? AND id <> ?
              AND deleted_at IS NULL AND replaced_file_id IS NULL
            ORDER BY id ASC
            LIMIT 1
        ');
        $stmt->execute([$userId, $sha256, $newFileId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return;
    }
    if (!$existing) {
        return;
    }
    $existingName = (string) ($existing['original_name'] ?? 'a file');
    datadock_add_notice(
        $pdo,
        $userId,
        'duplicate_file',
        'You already have this file',
        $name . ' matches ' . $existingName . '. The new copy was kept.',
        'edit_file.php?id=' . (int) $existing['id']
    );
}

/**
 * @return list<array<string, mixed>>
 */
function datadock_file_versions(PDO $pdo, int $fileId): array {
    if ($fileId < 1) {
        return [];
    }
    $stmt = $pdo->prepare('
        SELECT id, original_name, filesize, filetype, checksum_sha256, deleted_at, client_encrypted
        FROM files
        WHERE replaced_file_id = ?
        ORDER BY id DESC
        LIMIT 5
    ');
    $stmt->execute([$fileId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function datadock_trim_file_versions(PDO $pdo, int $fileId, int $keep = 5): void {
    $keep = max(1, $keep);
    $stmt = $pdo->prepare('SELECT id FROM files WHERE replaced_file_id = ? ORDER BY id DESC');
    $stmt->execute([$fileId]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    foreach (array_slice($ids, $keep) as $oldId) {
        $rowStmt = $pdo->prepare('SELECT * FROM files WHERE id = ?');
        $rowStmt->execute([$oldId]);
        $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            continue;
        }
        datadock_release_file_storage($pdo, $row);
        $pdo->prepare('DELETE FROM files WHERE id = ?')->execute([$oldId]);
    }
}

/**
 * @return array{ok:bool,error:?string}
 */
function datadock_restore_file_version(PDO $pdo, int $fileId, int $versionId, int $ownerId): array {
    $liveStmt = $pdo->prepare('SELECT * FROM files WHERE id = ? AND user_id = ? AND deleted_at IS NULL AND replaced_file_id IS NULL');
    $liveStmt->execute([$fileId, $ownerId]);
    $live = $liveStmt->fetch(PDO::FETCH_ASSOC);
    $versionStmt = $pdo->prepare('SELECT * FROM files WHERE id = ? AND replaced_file_id = ?');
    $versionStmt->execute([$versionId, $fileId]);
    $version = $versionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$live || !$version) {
        return ['ok' => false, 'error' => 'That version is not available.'];
    }
    $liveObject = (int) ($live['storage_object_id'] ?? 0);
    $versionObject = (int) ($version['storage_object_id'] ?? 0);
    if ($liveObject > 0) {
        datadock_increment_object_ref($pdo, $liveObject);
    }
    $pdo->prepare('
        INSERT INTO files
          (user_id, guest_id, storage_partition_id, folder_id, is_public, filename, storage_object_id, original_name,
           filetype, filesize, thumbnail_path, upload_date, expiry_date, checksum_md5, checksum_sha256,
           quarantine_status, mime_anomaly, deleted_at, purge_hold, download_count, client_encrypted, replaced_file_id)
        SELECT user_id, guest_id, storage_partition_id, folder_id, is_public, filename, storage_object_id, original_name,
               filetype, filesize, thumbnail_path, upload_date, expiry_date, checksum_md5, checksum_sha256,
               quarantine_status, mime_anomaly, UTC_TIMESTAMP(), 0, 0, client_encrypted, ?
        FROM files WHERE id = ?
    ')->execute([$fileId, $fileId]);
    if ($versionObject > 0) {
        datadock_increment_object_ref($pdo, $versionObject);
    }
    $pdo->prepare('
        UPDATE files
           SET filename = ?, storage_object_id = ?, original_name = ?, filetype = ?, filesize = ?,
               thumbnail_path = ?, checksum_md5 = ?, checksum_sha256 = ?, client_encrypted = ?
         WHERE id = ?
    ')->execute([
        $version['filename'],
        $versionObject > 0 ? $versionObject : null,
        $version['original_name'],
        $version['filetype'],
        $version['filesize'],
        $version['thumbnail_path'],
        $version['checksum_md5'],
        $version['checksum_sha256'],
        (int) ($version['client_encrypted'] ?? 0),
        $fileId,
    ]);
    if ($liveObject > 0) {
        $pdo->prepare('UPDATE storage_objects SET ref_count = ref_count - 1 WHERE id = ?')->execute([$liveObject]);
    }
    $version['thumbnail_path'] = null;
    datadock_release_file_storage($pdo, $version);
    $pdo->prepare('DELETE FROM files WHERE id = ?')->execute([$versionId]);
    datadock_trim_file_versions($pdo, $fileId, 5);
    return ['ok' => true, 'error' => null];
}

function datadock_create_drop_link(PDO $pdo, int $userId, int $folderId): ?string {
    $chk = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ?');
    $chk->execute([$folderId, $userId]);
    if (!$chk->fetch()) {
        return null;
    }
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $token = bin2hex(random_bytes(32));
        try {
            $pdo->prepare('INSERT INTO drop_links (user_id, folder_id, token) VALUES (?, ?, ?)')->execute([$userId, $folderId, $token]);
            return $token;
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
        }
    }
    return null;
}

/**
 * @return array<string, mixed>|null
 */
function datadock_drop_link_by_token(PDO $pdo, string $token): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare('
        SELECT d.id, d.user_id, d.folder_id, d.token, f.name AS folder_name
        FROM drop_links d
        INNER JOIN folders f ON f.id = d.folder_id AND f.user_id = d.user_id
        WHERE d.token = ?
    ');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function datadock_notice_feed_token(PDO $pdo, int $userId, bool $rotate = false): string {
    if (!$rotate) {
        $stmt = $pdo->prepare('SELECT notice_feed_token FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $existing = (string) ($stmt->fetchColumn() ?: '');
        if (preg_match('/^[a-f0-9]{64}$/', $existing)) {
            return $existing;
        }
    }
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $token = bin2hex(random_bytes(32));
        try {
            $pdo->prepare('UPDATE users SET notice_feed_token = ? WHERE id = ?')->execute([$token, $userId]);
            return $token;
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
        }
    }
    return '';
}

function datadock_user_id_for_notice_feed(PDO $pdo, string $token): int {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT id FROM users WHERE notice_feed_token = ?');
    $stmt->execute([$token]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function datadock_xml_text(string $value): string {
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function datadock_notice_link_absolute(string $link): string {
    $link = trim($link);
    if ($link === '' || str_contains($link, "\n") || str_contains($link, "\r")) {
        return '';
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $link) || str_starts_with($link, '//')) {
        return '';
    }
    if (!function_exists('datadock_public_url')) {
        return $link;
    }
    return datadock_public_url($link);
}

/**
 * @param list<array<string, mixed>> $notices
 */
function datadock_notices_atom(string $site, string $selfUrl, string $feedId, array $notices): string {
    $updated = gmdate('Y-m-d\TH:i:s\Z');
    if ($notices) {
        $stamp = strtotime((string) ($notices[0]['created_at'] ?? '') . ' UTC');
        if ($stamp) {
            $updated = gmdate('Y-m-d\TH:i:s\Z', $stamp);
        }
    }
    $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
    $xml .= '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
    $xml .= '<title>' . datadock_xml_text($site . ' notices') . '</title>' . "\n";
    $xml .= '<id>' . datadock_xml_text($feedId) . '</id>' . "\n";
    $xml .= '<updated>' . $updated . '</updated>' . "\n";
    $xml .= '<link rel="self" href="' . datadock_xml_text($selfUrl) . '"/>' . "\n";
    foreach ($notices as $notice) {
        $id = (int) ($notice['id'] ?? 0);
        $when = strtotime((string) ($notice['created_at'] ?? '') . ' UTC');
        $whenText = $when ? gmdate('Y-m-d\TH:i:s\Z', $when) : $updated;
        $xml .= "<entry>\n";
        $xml .= '<title>' . datadock_xml_text((string) ($notice['title'] ?? 'Notice')) . "</title>\n";
        $xml .= '<id>urn:datadock:notice:' . $id . "</id>\n";
        $xml .= '<updated>' . $whenText . "</updated>\n";
        $body = trim((string) ($notice['body'] ?? ''));
        if ($body !== '') {
            $xml .= '<summary>' . datadock_xml_text($body) . "</summary>\n";
        }
        $href = datadock_notice_link_absolute((string) ($notice['link_url'] ?? ''));
        if ($href !== '') {
            $xml .= '<link rel="alternate" href="' . datadock_xml_text($href) . "\"/>\n";
        }
        $xml .= "</entry>\n";
    }
    $xml .= "</feed>\n";
    return $xml;
}

function datadock_file_is_sealed(string $path): bool {
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return false;
    }
    $magic = fread($handle, 4);
    fclose($handle);
    return $magic === 'DDE1';
}

function datadock_paste_is_sealed(string $content): bool {
    return str_starts_with($content, 'datadock-enc:v1:');
}
