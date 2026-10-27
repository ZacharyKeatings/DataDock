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
require_once __DIR__ . '/includes/ingest_file.php';
require_once __DIR__ . '/includes/audit_log.php';

$fileId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
if ($isAdmin) {
    $stmt = $pdo->prepare('SELECT * FROM files WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$fileId]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM files WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
    $stmt->execute([$fileId, $userId]);
}
$file = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$file) {
    $_SESSION['flash_error'][] = 'File not found.';
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    datadock_reject_invalid_csrf('replace_file.php?id=' . $fileId);
    $upload = $_FILES['replacement'] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $_SESSION['flash_error'][] = 'Choose a file to upload.';
    } else {
        $result = datadock_replace_file_contents($pdo, $settings, $file, (string) $upload['tmp_name'], (string) $upload['name']);
        if (!$result['ok']) {
            $_SESSION['flash_error'][] = $result['error'];
        } else {
            datadock_log_activity($pdo, 'file_replaced', [
                'actor_user_id' => $userId,
                'file_id' => $fileId,
                'detail' => ['name' => (string) $upload['name']],
            ]);
            $_SESSION['flash_success'][] = 'File contents replaced. Existing links still point at this file. The previous bytes stay in trash until retention removes them.';
            header('Location: dashboard.php');
            exit;
        }
    }
}

$pageTitle = 'Replace file';
require __DIR__ . '/includes/header.php';
?>
<div class="page-section">
    <h2 class="page-title">Replace contents</h2>
    <p class="page-description">Upload new bytes for <strong><?= sanitize_data($file['original_name'] ?? 'file') ?></strong>. The file id, shares, and links stay the same.</p>
    <form method="post" enctype="multipart/form-data" class="form">
        <?= datadock_csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $fileId ?>">
        <div class="form-group">
            <label for="replacement">New file</label>
            <input type="file" name="replacement" id="replacement" required>
        </div>
        <button type="submit" class="btn btn-primary">Replace</button>
        <a href="dashboard.php" class="btn btn-quiet">Cancel</a>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
