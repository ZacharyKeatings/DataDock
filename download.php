<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/includes/hotlink_log.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/access_control.php';
require_once __DIR__ . '/includes/user_trust.php';

/**
 * Send Content-Disposition header: attachment for risky types (prevent inline execution), attachment for others.
 */
function send_download_disposition(string $mime, string $filename): void {
    $disposition = (is_risky_mime_for_inline($mime) ? 'attachment' : 'attachment');
    $safeName = str_replace(['"', "\r", "\n"], ['%22', '', ''], $filename);
    header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
}

function datadock_send_file_stream(PDO $pdo, array $file, array $settings, string $logAction, array $logOpts, bool $logDownloadEventForOwner): void {
    $fileId = (int) $file['id'];
    $path = datadock_file_main_path($pdo, $file);
    if (!file_exists($path)) {
        http_response_code(404);
        echo 'File missing.';
        exit;
    }
    $resourceMap = [
        'download' => 'download',
        'download_public' => 'public_download',
        'download_onetime' => 'onetime_download',
        'download_signed' => 'public_download',
    ];
    $hot = $resourceMap[$logAction] ?? 'download';
    datadock_log_hotlink_if_external($pdo, $settings, $hot, $fileId);
    datadock_log_activity($pdo, $logAction, $logOpts);
    $pdo->prepare("UPDATE files SET download_count = COALESCE(download_count, 0) + 1 WHERE id = ?")->execute([$fileId]);
    if ($logDownloadEventForOwner) {
        datadock_log_file_download_event($pdo, $fileId);
    }
    header('Content-Description: File Transfer');
    header('Content-Type: ' . ($file['filetype'] ?? 'application/octet-stream'));
    send_download_disposition($file['filetype'] ?? 'application/octet-stream', basename($file['original_name'] ?? $file['filename']));
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$publicBrowsingEnabled = !empty($settings['public_browsing_enabled']);

// Unlock form (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['download_access'])) {
    $fid = (int) ($_POST['file_id'] ?? 0);
    $next = (string) ($_POST['next'] ?? 'download.php');
    if (!preg_match('#^[a-zA-Z0-9_./?=&+%:-]+$#', $next) || strpos($next, '..') !== false) {
        $next = 'download.php';
    }
    $pw = (string) ($_POST['access_password'] ?? '');
    if ($fid > 0) {
        $stmt = $pdo->prepare("SELECT * FROM files f WHERE f.id = ? AND f.deleted_at IS NULL AND (f.quarantine_status = 'approved' OR f.quarantine_status IS NULL)");
        $stmt->execute([$fid]);
        $frow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($frow && !empty($frow['access_password_hash']) && password_verify($pw, $frow['access_password_hash'])) {
            datadock_session_grant_file_access($fid);
        } else {
            $_SESSION['flash_error'][] = '❌ Incorrect download password.';
        }
    }
    header('Location: ' . $next);
    exit;
}

// HMAC-signed temporary URL (no DB token row)
if (isset($_GET['id'], $_GET['exp'], $_GET['sig']) && is_numeric($_GET['id']) && is_numeric($_GET['exp'])) {
    $fileId = (int) $_GET['id'];
    $exp = (int) $_GET['exp'];
    $sig = (string) $_GET['sig'];
    if (!datadock_signed_download_verify($pdo, $fileId, $exp, $sig)) {
        http_response_code(403);
        echo 'Invalid or expired link.';
        exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND deleted_at IS NULL AND (quarantine_status = 'approved' OR quarantine_status IS NULL) AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$file) {
        http_response_code(404);
        echo 'File not found.';
        exit;
    }
    if (!datadock_anonymous_access_allowed($file, true)) {
        http_response_code(403);
        echo 'Access denied (IP not allowed).';
        exit;
    }
    $path = datadock_file_main_path($pdo, $file);
    if (!file_exists($path)) {
        http_response_code(404);
        echo 'File missing.';
        exit;
    }
    datadock_send_file_stream($pdo, $file, $settings, 'download_signed', [
        'file_id' => $fileId,
        'detail' => ['name' => $file['original_name'] ?? $file['filename'], 'signed' => true],
    ], true);
}

// Token-based download (multi-use / expiry supported)
if (isset($_GET['token']) && $_GET['token'] !== '') {
    $token = trim($_GET['token']);
    $stmt = $pdo->prepare("
        SELECT f.*, dt.expires_at AS dt_expires_at, dt.max_uses AS dt_max_uses, dt.use_count AS dt_use_count
        FROM files f
        JOIN download_tokens dt ON f.id = dt.file_id
        WHERE dt.token = ? AND f.deleted_at IS NULL AND (f.quarantine_status = 'approved' OR f.quarantine_status IS NULL)
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $tokenValid = false;
    if ($row) {
        $dtExpires = $row['dt_expires_at'] ?? null;
        if ($dtExpires !== null && $dtExpires !== '') {
            $ts = strtotime((string) $dtExpires . ' UTC');
            if ($ts !== false && $ts < time()) {
                $pdo->prepare('DELETE FROM download_tokens WHERE token = ?')->execute([$token]);
                $row = false;
            }
        }
    }
    if ($row) {
        $maxUses = (int) ($row['dt_max_uses'] ?? 1);
        $useCount = (int) ($row['dt_use_count'] ?? 0);
        if ($maxUses > 0 && $useCount >= $maxUses) {
            $row = false;
        } else {
            $tokenValid = true;
        }
    }
    if ($row && $tokenValid) {
        $file = $row;
        unset($file['dt_expires_at'], $file['dt_max_uses'], $file['dt_use_count']);
        $fid = (int) $file['id'];
        if (!datadock_ip_allowlist_allows($file['ip_allowlist'] ?? null, datadock_client_ip())) {
            http_response_code(403);
            echo 'Access denied (IP not allowed).';
            exit;
        }
        if (!empty($file['access_password_hash']) && !datadock_session_has_file_access($fid)) {
            $q = 'token=' . rawurlencode($token);
            datadock_render_download_password_page((string) ($file['original_name'] ?? $file['filename']), '', ['file_id' => $fid, 'next' => 'download.php?' . $q]);
        }
        $path = datadock_file_main_path($pdo, $file);
        if (file_exists($path)) {
            $maxUses = (int) ($row['dt_max_uses'] ?? 1);
            $useCount = (int) ($row['dt_use_count'] ?? 0);
            $newUse = $useCount + 1;
            if ($maxUses > 0 && $newUse >= $maxUses) {
                $pdo->prepare('DELETE FROM download_tokens WHERE token = ?')->execute([$token]);
            } else {
                $pdo->prepare('UPDATE download_tokens SET use_count = use_count + 1 WHERE token = ?')->execute([$token]);
            }
            datadock_send_file_stream($pdo, $file, $settings, 'download_onetime', [
                'file_id' => $fid,
                'detail' => ['name' => $file['original_name'] ?? $file['filename']],
            ], true);
        }
    }
    if (empty($userId)) {
        header('Location: login.php');
        exit;
    }
    $_SESSION['flash_error'][] = '❌ Download link expired or invalid.';
    header('Location: dashboard.php');
    exit;
}

// Anonymous download of public files
if (empty($userId) && isset($_GET['id']) && is_numeric($_GET['id']) && $publicBrowsingEnabled) {
    $fileId = (int) $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND is_public = 1 AND deleted_at IS NULL AND (quarantine_status = 'approved' OR quarantine_status IS NULL) AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($file) {
        if (!datadock_ip_allowlist_allows($file['ip_allowlist'] ?? null, datadock_client_ip())) {
            http_response_code(403);
            echo 'Access denied (IP not allowed).';
            exit;
        }
        if (!empty($file['access_password_hash']) && !datadock_session_has_file_access($fileId)) {
            $q = 'id=' . $fileId;
            datadock_render_download_password_page((string) ($file['original_name'] ?? $file['filename']), 'index.php', ['file_id' => $fileId, 'next' => 'download.php?' . $q]);
        }
        $path = datadock_file_main_path($pdo, $file);
        if (file_exists($path)) {
            datadock_send_file_stream($pdo, $file, $settings, 'download_public', [
                'file_id' => $fileId,
                'detail' => ['name' => $file['original_name'] ?? $file['filename']],
            ], true);
        }
    }
}

require_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_error'][] = '❌ Invalid download request.';
    header('Location: dashboard.php');
    exit;
}

$fileId = (int) $_GET['id'];

if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$fileId]);
} else {
    $stmt = $pdo->prepare("
        SELECT f.* FROM files f
        LEFT JOIN file_shares fs ON f.id = fs.file_id AND fs.shared_with_user_id = ?
        WHERE f.id = ? AND (f.user_id = ? OR fs.id IS NOT NULL)
        AND f.deleted_at IS NULL
        AND (f.quarantine_status = 'approved' OR f.quarantine_status IS NULL)
    ");
    $stmt->execute([$userId, $fileId, $userId]);
}
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    $_SESSION['flash_error'][] = '❌ File not found or permission denied.';
    header('Location: dashboard.php');
    exit;
}

$path = datadock_file_main_path($pdo, $file);
if (!file_exists($path)) {
    $_SESSION['flash_error'][] = '❌ File is missing from server.';
    header('Location: dashboard.php');
    exit;
}

$ownerId = isset($file['user_id']) ? (int) $file['user_id'] : 0;
$isOwner = $ownerId > 0 && $ownerId === (int) $userId;
$logEvent = !$isOwner;

datadock_send_file_stream($pdo, $file, $settings, 'download', [
    'actor_user_id' => $userId,
    'file_id' => $fileId,
    'detail' => ['name' => $file['original_name'] ?? $file['filename']],
], $logEvent);
