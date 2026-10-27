<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/access_control.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();

function datadock_preview_kind(string $mime): ?string {
    $mime = strtolower(trim(explode(';', $mime)[0]));
    if ($mime === '' || is_risky_mime_for_inline($mime)) {
        return null;
    }
    if ($mime === 'image/svg+xml') {
        return null;
    }
    if (str_starts_with($mime, 'image/')) {
        return 'image';
    }
    if (str_starts_with($mime, 'audio/')) {
        return 'audio';
    }
    if (str_starts_with($mime, 'video/')) {
        return 'video';
    }
    if ($mime === 'text/plain') {
        return 'text';
    }
    return null;
}

$fileId = (int) ($_GET['id'] ?? 0);
if ($fileId < 1) {
    datadock_render_status_page(404, 'File not found', 'This preview is not available.');
}
$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND deleted_at IS NULL AND (quarantine_status = 'approved' OR quarantine_status IS NULL)");
$stmt->execute([$fileId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$file) {
    datadock_render_status_page(404, 'File not found', 'This preview is not available.');
}
$kind = datadock_preview_kind((string) ($file['filetype'] ?? ''));
if ($kind === null) {
    header('Location: download.php?id=' . $fileId);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$ownerId = (int) ($file['user_id'] ?? 0);
$allowed = $isAdmin || ($ownerId > 0 && $ownerId === $userId);
if (!$allowed && $userId > 0) {
    $share = $pdo->prepare('SELECT id FROM file_shares WHERE file_id = ? AND shared_with_user_id = ?');
    $share->execute([$fileId, $userId]);
    $allowed = (bool) $share->fetch();
}
if (!$allowed && !empty($file['is_public']) && !empty($settings['public_browsing_enabled'])) {
    $exp = $file['expiry_date'] ?? null;
    $fresh = $exp === null || $exp === '' || (strtotime((string) $exp . ' UTC') ?: 0) > time();
    $allowed = $fresh;
}
if (!$allowed) {
    datadock_render_status_page(403, 'Preview unavailable', 'You do not have access to this file.');
}
if (!empty($file['access_password_hash']) && $ownerId !== $userId && !$isAdmin && !datadock_session_has_file_access($fileId)) {
    datadock_render_download_password_page((string) ($file['original_name'] ?? 'file'), '', ['file_id' => $fileId, 'next' => 'preview.php?id=' . $fileId]);
}
$path = datadock_file_main_path($pdo, $file);
if (!is_readable($path)) {
    datadock_render_status_page(404, 'File missing', 'This file is no longer on the server.');
}

$raw = isset($_GET['raw']);
$name = (string) ($file['original_name'] ?? 'file');
if ($raw || $kind !== 'text') {
    if ($raw || $kind === 'image' || $kind === 'audio' || $kind === 'video') {
        if ($kind === 'text' || $raw) {
            header('X-Content-Type-Options: nosniff');
            header('Content-Security-Policy: default-src \'none\'; sandbox');
            header('Content-Type: ' . ($kind === 'text' ? 'text/plain; charset=UTF-8' : (string) $file['filetype']));
            header('Content-Disposition: inline; filename="' . str_replace('"', '', basename($name)) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }
    }
}

$pageTitle = $name;
require __DIR__ . '/includes/header.php';
$safeName = sanitize_data($name);
$media = 'preview.php?id=' . $fileId . '&raw=1';
echo '<div class="page-section"><h2 class="page-title">' . $safeName . '</h2>';
echo '<p class="page-description"><a href="download.php?id=' . $fileId . '">Download</a></p>';
if ($kind === 'image') {
    echo '<img src="' . sanitize_data($media) . '" alt="' . $safeName . '" class="preview-full">';
} elseif ($kind === 'audio') {
    echo '<audio controls src="' . sanitize_data($media) . '" class="preview-media">Audio preview is not supported in this browser.</audio>';
} elseif ($kind === 'video') {
    echo '<video controls src="' . sanitize_data($media) . '" class="preview-media">Video preview is not supported in this browser.</video>';
} else {
    $text = (string) file_get_contents($path, false, null, 0, 1048576);
    echo '<pre class="preview-text">' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
    if (filesize($path) > 1048576) {
        echo '<p class="form-hint">Showing the first 1 MB. Download the file for the rest.</p>';
    }
}
echo '</div>';
require __DIR__ . '/includes/footer.php';
