<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/read_only.php';
require_once __DIR__ . '/includes/ingest_file.php';
require_once __DIR__ . '/includes/personal.php';
require_once __DIR__ . '/includes/audit_log.php';

$settings = datadock_load_settings();
$token = (string) ($_GET['t'] ?? $_POST['token'] ?? '');
$link = datadock_drop_link_by_token($pdo, $token);
if (!$link) {
    http_response_code(404);
    $pageTitle = 'Drop link unavailable';
    require __DIR__ . '/includes/header.php';
    echo '<div class="page-section"><h2 class="page-title">Drop link unavailable</h2><p>This link does not exist, or its folder was removed.</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$ownerId = (int) $link['user_id'];
$folderId = (int) $link['folder_id'];
$defaults = datadock_effective_upload_defaults($pdo, $ownerId, $folderId, (string) ($settings['default_file_expiry'] ?? 'never'));
$tosEnabled = !empty($settings['tos_enabled']);
$tosText = trim((string) ($settings['tos_text'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    datadock_block_if_read_only($settings, 'drop.php?t=' . $token, 'drop.php?t=' . $token);
    datadock_reject_invalid_csrf('drop.php?t=' . $token);
    if ($tosEnabled && $tosText !== '' && !isset($_POST['tos_accepted'])) {
        $_SESSION['flash_error'][] = 'You must accept the Terms of Service to upload.';
    }
    $clientIp = get_client_ip();
    if (empty($_SESSION['flash_error']) && !check_upload_rate_limit($pdo, $ownerId, $clientIp, $settings)) {
        $_SESSION['flash_error'][] = 'Too many uploads. Please try again in a few minutes.';
    }
    $upload = $_FILES['upload'] ?? null;
    if (empty($_SESSION['flash_error']) && (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
        $_SESSION['flash_error'][] = 'Choose a file to add.';
    }
    if (empty($_SESSION['flash_error'])) {
        $stored = datadock_ingest_local_file($pdo, $settings, [
            'source_path' => (string) $upload['tmp_name'],
            'original_name' => (string) $upload['name'],
            'file_size' => (int) $upload['size'],
            'user_id' => $ownerId,
            'folder_id' => $folderId,
            'is_public' => $defaults['public'] === 1 ? 1 : 0,
            'expiry_ts' => datadock_expiry_timestamp($defaults['expiry']),
            'relocate' => 'upload',
        ]);
        if (!$stored['ok']) {
            $_SESSION['flash_error'][] = $stored['error'] ?? 'The file could not be stored.';
        } else {
            record_upload_rate_limit($pdo, $clientIp, $ownerId, 1);
            datadock_add_notice(
                $pdo,
                $ownerId,
                'drop_upload',
                'A file arrived in ' . (string) $link['folder_name'],
                (string) $upload['name'] . ' was added through your drop link.',
                'dashboard.php?folder=' . $folderId
            );
            datadock_log_activity($pdo, 'drop_upload', [
                'actor_user_id' => $ownerId,
                'file_id' => (int) ($stored['file_id'] ?? 0),
                'detail' => ['name' => (string) $upload['name'], 'folder_id' => $folderId],
            ]);
            $_SESSION['flash_success'][] = 'File received. The folder owner can see it in their files.';
            header('Location: drop.php?t=' . rawurlencode($token));
            exit;
        }
    }
}

$pageTitle = 'Add a file';
require __DIR__ . '/includes/header.php';
?>
<div class="page-section">
    <h2 class="page-title">Add a file</h2>
    <p class="page-description">This upload goes into <strong><?= sanitize_data((string) $link['folder_name']) ?></strong>. You do not need an account. Size limits and the folder owner’s quota still apply.</p>
    <form method="post" enctype="multipart/form-data" action="drop.php?t=<?= sanitize_data($token) ?>" class="form">
        <?= datadock_csrf_field() ?>
        <input type="hidden" name="token" value="<?= sanitize_data($token) ?>">
        <?php if ($tosEnabled && $tosText !== ''): ?>
            <div class="tos-block">
                <div class="tos-text"><?= nl2br(sanitize_data($tosText)) ?></div>
                <label class="tos-checkbox">
                    <input type="checkbox" name="tos_accepted" required>
                    I agree to the Terms of Service / Acceptable Use
                </label>
            </div>
        <?php endif; ?>
        <div class="form-group">
            <label for="upload">File</label>
            <input type="file" name="upload" id="upload" required>
        </div>
        <button type="submit" class="btn btn-primary">Upload</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
