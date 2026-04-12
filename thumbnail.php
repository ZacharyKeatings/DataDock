<?php
/**
 * Serve thumbnail images. Used when thumbnails may be stored outside the web root
 * (e.g. with custom storage_base_path). Looks up file by ID and streams the image.
 */
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
require_once __DIR__ . '/includes/hotlink_log.php';
require_once __DIR__ . '/includes/access_control.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit;
}

$fileId = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND deleted_at IS NULL AND thumbnail_path IS NOT NULL AND thumbnail_path != '' AND (quarantine_status = 'approved' OR quarantine_status IS NULL)");
$stmt->execute([$fileId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$ownerId = (int) ($row['user_id'] ?? 0);
$allowed = $isAdmin || ($userId && $ownerId === (int) $userId);
if (!$allowed && $userId) {
    $chk = $pdo->prepare('SELECT 1 FROM file_shares WHERE file_id = ? AND shared_with_user_id = ? LIMIT 1');
    $chk->execute([$fileId, $userId]);
    $allowed = (bool) $chk->fetchColumn();
}
if (!$allowed && isset($_GET['sf']) && $_GET['sf'] !== '') {
    $sf = trim((string) $_GET['sf']);
    if (strlen($sf) === 64 && ctype_xdigit($sf)) {
        $st = $pdo->prepare('
            SELECT sf.expires_at FROM share_folders sf
            INNER JOIN share_folder_files sff ON sff.share_folder_id = sf.id AND sff.file_id = ?
            WHERE sf.token = ?
        ');
        $st->execute([$fileId, $sf]);
        $srow = $st->fetch(PDO::FETCH_ASSOC);
        if ($srow) {
            $sexp = $srow['expires_at'] ?? null;
            $expired = false;
            if ($sexp !== null && $sexp !== '') {
                $ts = strtotime((string) $sexp . ' UTC');
                if ($ts !== false && $ts < time()) {
                    $expired = true;
                }
            }
            if (!$expired) {
                if (!datadock_ip_allowlist_allows($row['ip_allowlist'] ?? null, datadock_client_ip())) {
                    http_response_code(403);
                    exit;
                }
                if (!empty($row['access_password_hash']) && !datadock_session_has_file_access($fileId)) {
                    http_response_code(403);
                    exit;
                }
                $allowed = true;
            }
        }
    }
}
if (!$allowed) {
    $publicOk = !empty($settings['public_browsing_enabled'])
        && !empty($row['is_public'])
        && (empty($row['expiry_date']) || strtotime((string) $row['expiry_date'] . ' UTC') > time());
    if ($publicOk) {
        if (!datadock_ip_allowlist_allows($row['ip_allowlist'] ?? null, datadock_client_ip())) {
            http_response_code(403);
            exit;
        }
        if (!empty($row['access_password_hash']) && !datadock_session_has_file_access($fileId)) {
            http_response_code(403);
            exit;
        }
        $allowed = true;
    }
}
if (!$allowed) {
    http_response_code(403);
    exit;
}

$path = datadock_file_thumb_path($pdo, $row);
if ($path === null) {
    http_response_code(404);
    exit;
}
if (!file_exists($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

datadock_log_hotlink_if_external($pdo, $settings, 'thumbnail', $fileId);
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=86400');
readfile($path);
exit;
