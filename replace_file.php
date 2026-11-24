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
        $result = datadock_replace_file_contents($pdo, $settings, $file, (string) $upload['tmp_name'], (string) $upload['name'], !empty($_POST['client_encrypted']));
        if (!$result['ok']) {
            $_SESSION['flash_error'][] = $result['error'];
        } else {
            datadock_log_activity($pdo, 'file_replaced', [
                'actor_user_id' => $userId,
                'file_id' => $fileId,
                'detail' => ['name' => (string) $upload['name']],
            ]);
            $_SESSION['flash_success'][] = 'File contents replaced. Existing links still point at this file. The previous copy stays on the file page until trash retention removes it.';
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
        <div class="form-group settings-row-checkbox">
            <label>
                <input type="checkbox" id="client_encrypt">
                Encrypt in the browser
            </label>
            <p class="form-hint">The passphrase stays on this device. Files over 48 MB are left unencrypted.</p>
            <input type="password" id="client_encrypt_pass" autocomplete="off" placeholder="Passphrase" hidden>
        </div>
        <input type="hidden" name="client_encrypted" id="client_encrypted" value="0">
        <button type="submit" class="btn btn-primary">Replace</button>
        <a href="dashboard.php" class="btn btn-quiet">Cancel</a>
    </form>
</div>
<script src="<?= sanitize_data(app_asset_url('assets/client_encrypt.js')) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form.form');
    var box = document.getElementById('client_encrypt');
    var pass = document.getElementById('client_encrypt_pass');
    var flag = document.getElementById('client_encrypted');
    var input = document.getElementById('replacement');
    if (!form || !box || !input) return;
    box.addEventListener('change', function () { pass.hidden = !box.checked; });
    form.addEventListener('submit', function (event) {
        if (!box.checked || form.dataset.sealed === '1') return;
        event.preventDefault();
        var file = input.files && input.files[0];
        if (!file) return;
        if (!pass.value) {
            window.alert('Enter a passphrase. It stays in this browser.');
            return;
        }
        if (file.size > datadockEncrypt.limit) {
            window.alert('Encryption keeps the file in memory. Leave this one unencrypted or choose a file of 48 MB or less.');
            return;
        }
        file.arrayBuffer().then(function (buffer) {
            return datadockEncrypt.encryptBytes(buffer, pass.value);
        }).then(function (sealed) {
            var transfer = new DataTransfer();
            transfer.items.add(new File([sealed], file.name, { type: 'application/octet-stream' }));
            input.files = transfer.files;
            flag.value = '1';
            pass.value = '';
            form.dataset.sealed = '1';
            form.submit();
        }).catch(function (error) {
            window.alert(error && error.message ? error.message : 'Could not encrypt this file.');
        });
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
