<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
require_once __DIR__ . '/includes/read_only.php';
datadock_block_if_read_only($settings);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ids']) || !is_array($_POST['ids'])) {
    $_SESSION['flash_error'][] = '❌ No files selected.';
    header('Location: dashboard.php');
    exit;
}

$ids = array_values(array_filter(array_map('intval', $_POST['ids'])));
if ($ids === []) {
    $_SESSION['flash_error'][] = '❌ No valid files selected.';
    header('Location: dashboard.php');
    exit;
}

$folderId = isset($_POST['folder_id']) ? (int) $_POST['folder_id'] : 0;
if ($folderId < 0) {
    $folderId = 0;
}

$userId = (int) $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$placeholders = implode(',', array_fill(0, count($ids), '?'));

if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT id, user_id FROM files WHERE id IN ($placeholders) AND deleted_at IS NULL");
    $stmt->execute($ids);
} else {
    $stmt = $pdo->prepare("SELECT id, user_id FROM files WHERE id IN ($placeholders) AND deleted_at IS NULL AND user_id = ?");
    $stmt->execute(array_merge($ids, [$userId]));
}
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

$moved = 0;
$skipped = 0;
$targetFolder = $folderId > 0 ? $folderId : null;

foreach ($files as $file) {
    $ownerId = (int) ($file['user_id'] ?? 0);
    if ($targetFolder !== null) {
        $chk = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ?');
        $chk->execute([$targetFolder, $ownerId]);
        if (!$chk->fetch()) {
            $skipped++;
            continue;
        }
    }
    if ($isAdmin) {
        $pdo->prepare('UPDATE files SET folder_id = ? WHERE id = ?')->execute([$targetFolder, (int) $file['id']]);
    } else {
        $pdo->prepare('UPDATE files SET folder_id = ? WHERE id = ? AND user_id = ?')->execute([$targetFolder, (int) $file['id'], $userId]);
    }
    $moved++;
}

if ($moved > 0) {
    $_SESSION['flash_success'][] = '✅ Moved ' . $moved . ' file' . ($moved === 1 ? '' : 's') . '.';
}
if ($skipped > 0) {
    $_SESSION['flash_warning'][] = '⚠️ Skipped ' . $skipped . ' file' . ($skipped === 1 ? '' : 's') . ' because the folder belongs to another account.';
}
if ($moved === 0 && $skipped === 0) {
    $_SESSION['flash_error'][] = '❌ No files were moved.';
}

$loc = 'dashboard.php';
if ($folderId > 0 && $skipped === 0) {
    $loc .= '?folder=' . $folderId;
}
header('Location: ' . $loc);
exit;
