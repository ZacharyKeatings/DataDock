<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/access_control.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/folder_links.php';

$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$link = datadock_folder_link_by_token($pdo, $token);
if (!$link) {
    datadock_render_status_page(404, 'Link unavailable', 'This folder link does not exist or has expired.');
}
$linkId = (int) $link['id'];
if (!datadock_ip_allowlist_allows($link['ip_allowlist'] ?? null, datadock_client_ip())) {
    datadock_render_status_page(403, 'Access denied', 'This link is limited to specific IP addresses.');
}
if (!empty($link['access_password_hash']) && !datadock_folder_link_unlocked($linkId)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (password_verify((string) ($_POST['access_password'] ?? ''), (string) $link['access_password_hash'])) {
            datadock_folder_link_grant($linkId);
            header('Location: folder_link.php?t=' . rawurlencode($token));
            exit;
        }
        $_SESSION['flash_error'][] = 'Incorrect password.';
    }
    $pageTitle = 'Folder link';
    require __DIR__ . '/includes/header.php';
    echo '<div class="page-section"><h2 class="page-title">This folder is locked</h2><form method="post" class="form">';
    echo '<input type="hidden" name="t" value="' . sanitize_data($token) . '">';
    echo '<div class="form-group"><label for="access_password">Password</label><input type="password" name="access_password" id="access_password" required></div>';
    echo '<button type="submit" class="btn btn-primary">Unlock</button></form></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$rootId = (int) $link['folder_id'];
$ownerId = (int) $link['user_id'];
$viewId = (int) ($_GET['folder'] ?? $rootId);
if (!datadock_folder_is_under($pdo, $viewId, $rootId, $ownerId)) {
    $viewId = $rootId;
}
$folderStmt = $pdo->prepare('SELECT id, name, parent_id FROM folders WHERE id = ? AND user_id = ?');
$folderStmt->execute([$viewId, $ownerId]);
$folder = $folderStmt->fetch(PDO::FETCH_ASSOC);
$childFolders = $pdo->prepare('SELECT id, name FROM folders WHERE parent_id = ? AND user_id = ? ORDER BY name');
$childFolders->execute([$viewId, $ownerId]);
$folders = $childFolders->fetchAll(PDO::FETCH_ASSOC);
$filesStmt = $pdo->prepare("SELECT id, original_name, filesize, filetype, thumbnail_path FROM files WHERE folder_id = ? AND user_id = ? AND deleted_at IS NULL AND (quarantine_status = 'approved' OR quarantine_status IS NULL) AND (expiry_date IS NULL OR expiry_date > UTC_TIMESTAMP()) ORDER BY original_name");
$filesStmt->execute([$viewId, $ownerId]);
$files = $filesStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = (string) ($folder['name'] ?? 'Folder');
require __DIR__ . '/includes/header.php';
?>
<div class="page-section">
    <h2 class="page-title"><?= sanitize_data($folder['name'] ?? 'Folder') ?></h2>
    <p class="page-description">This link follows the folder. New and removed files show up here.</p>
    <?php if ($viewId !== $rootId): ?>
        <?php
        $parent = (int) ($folder['parent_id'] ?? 0);
        $back = datadock_folder_is_under($pdo, $parent, $rootId, $ownerId) ? $parent : $rootId;
        ?>
        <p><a href="folder_link.php?t=<?= rawurlencode($token) ?>&amp;folder=<?= (int) $back ?>">Up</a></p>
    <?php endif; ?>
    <?php if (!$folders && !$files): ?>
        <div class="empty-state"><p>This folder is empty.</p></div>
    <?php else: ?>
        <div class="file-list">
            <?php foreach ($folders as $child): ?>
                <div class="file-row">
                    <div class="file-name-cell"><a href="folder_link.php?t=<?= rawurlencode($token) ?>&amp;folder=<?= (int) $child['id'] ?>"><?= sanitize_data($child['name']) ?></a></div>
                </div>
            <?php endforeach; ?>
            <?php foreach ($files as $file): ?>
                <div class="file-row">
                    <div class="file-name-cell"><?= sanitize_data($file['original_name']) ?></div>
                    <div><?= format_filesize($file['filesize']) ?></div>
                    <div><a class="btn btn-primary btn-small" href="download.php?fl=<?= rawurlencode($token) ?>&amp;id=<?= (int) $file['id'] ?>">Download</a></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
