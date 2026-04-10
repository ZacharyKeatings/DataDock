<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/includes/hotlink_log.php';
require_once __DIR__ . '/includes/audit_log.php';

/**
 * Send Content-Disposition header: attachment for risky types (prevent inline execution), attachment for others.
 */
function send_download_disposition(string $mime, string $filename): void {
    $disposition = (is_risky_mime_for_inline($mime) ? 'attachment' : 'attachment');
    $safeName = str_replace(['"', "\r", "\n"], ['%22', '', ''], $filename);
    header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
}

$userId = $_SESSION['user_id'] ?? null;
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$publicBrowsingEnabled = !empty($settings['public_browsing_enabled']);

// One-time download via token (no login required)
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);
    $stmt = $pdo->prepare("SELECT f.* FROM files f JOIN download_tokens dt ON f.id = dt.file_id WHERE dt.token = ? AND f.deleted_at IS NULL AND (f.quarantine_status = 'approved' OR f.quarantine_status IS NULL)");
    $stmt->execute([$token]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($file) {
        $pdo->prepare("DELETE FROM download_tokens WHERE token = ?")->execute([$token]);
        $path = datadock_file_main_path($pdo, $file);
        if (file_exists($path)) {
            datadock_log_hotlink_if_external($pdo, $settings, 'onetime_download', (int) $file['id']);
            datadock_log_activity($pdo, 'download_onetime', [
                'file_id' => (int) $file['id'],
                'detail' => ['name' => $file['original_name'] ?? $file['filename']],
            ]);
            $pdo->prepare("UPDATE files SET download_count = COALESCE(download_count, 0) + 1 WHERE id = ?")->execute([$file['id']]);
            header('Content-Description: File Transfer');
            header('Content-Type: ' . ($file['filetype'] ?? 'application/octet-stream'));
            send_download_disposition($file['filetype'] ?? 'application/octet-stream', basename($file['original_name'] ?? $file['filename']));
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }
    }
    if (empty($userId)) {
        header("Location: login.php");
        exit;
    }
    $_SESSION['flash_error'][] = "❌ Download link expired or invalid.";
    header("Location: dashboard.php");
    exit;
}

// Anonymous download of public files (when public browsing is enabled)
if (empty($userId) && isset($_GET['id']) && is_numeric($_GET['id']) && $publicBrowsingEnabled) {
    $fileId = (int) $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND is_public = 1 AND deleted_at IS NULL AND (quarantine_status = 'approved' OR quarantine_status IS NULL) AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP())");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($file) {
        $path = datadock_file_main_path($pdo, $file);
        if (file_exists($path)) {
            datadock_log_hotlink_if_external($pdo, $settings, 'public_download', $fileId);
            datadock_log_activity($pdo, 'download_public', [
                'file_id' => $fileId,
                'detail' => ['name' => $file['original_name'] ?? $file['filename']],
            ]);
            $pdo->prepare("UPDATE files SET download_count = COALESCE(download_count, 0) + 1 WHERE id = ?")->execute([$fileId]);
            header('Content-Description: File Transfer');
            header('Content-Type: ' . ($file['filetype'] ?? 'application/octet-stream'));
            send_download_disposition($file['filetype'] ?? 'application/octet-stream', basename($file['original_name'] ?? $file['filename']));
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }
    }
}

require_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_error'][] = "❌ Invalid download request.";
    header("Location: dashboard.php");
    exit;
}

$fileId = (int) $_GET['id'];

if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$fileId]);
} else {
    // Owner OR shared with this user; exclude quarantined and trashed
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
    $_SESSION['flash_error'][] = "❌ File not found or permission denied.";
    header("Location: dashboard.php");
    exit;
}

$path = datadock_file_main_path($pdo, $file);
if (!file_exists($path)) {
    $_SESSION['flash_error'][] = "❌ File is missing from server.";
    header("Location: dashboard.php");
    exit;
}

datadock_log_hotlink_if_external($pdo, $settings, 'download', $fileId);
datadock_log_activity($pdo, 'download', [
    'actor_user_id' => $userId,
    'file_id' => $fileId,
    'detail' => ['name' => $file['original_name'] ?? $file['filename']],
]);
$pdo->prepare("UPDATE files SET download_count = COALESCE(download_count, 0) + 1 WHERE id = ?")->execute([$fileId]);

header('Content-Description: File Transfer');
header('Content-Type: ' . ($file['filetype'] ?? 'application/octet-stream'));
send_download_disposition($file['filetype'] ?? 'application/octet-stream', basename($file['original_name'] ?? $file['filename']));
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
