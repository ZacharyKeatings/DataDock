<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/access_control.php';
require_once __DIR__ . '/includes/settings_loader.php';

$settings = datadock_load_settings();
$fileId = (int) ($_GET['id'] ?? 0);
if ($fileId < 1) {
    datadock_render_status_page(404, 'File not found', 'This file is not available.');
}
$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND deleted_at IS NULL AND replaced_file_id IS NULL AND (quarantine_status = 'approved' OR quarantine_status IS NULL)");
$stmt->execute([$fileId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$file) {
    datadock_render_status_page(404, 'File not found', 'This file is not available.');
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
    datadock_render_status_page(403, 'File unavailable', 'You do not have access to this file.');
}
$isOwner = $ownerId > 0 && $ownerId === $userId;
if (!$isOwner && !$isAdmin && !datadock_ip_allowlist_allows($file['ip_allowlist'] ?? null, datadock_client_ip())) {
    datadock_render_status_page(403, 'Access denied', 'This file is limited to specific IP addresses.');
}
if (!empty($file['access_password_hash']) && !$isOwner && !$isAdmin && !datadock_session_has_file_access($fileId)) {
    datadock_render_download_password_page((string) ($file['original_name'] ?? 'file'), '', ['file_id' => $fileId, 'next' => 'decrypt.php?id=' . $fileId]);
}

$name = (string) ($file['original_name'] ?? 'file');
$pageTitle = 'Decrypt file';
require __DIR__ . '/includes/header.php';
?>
<div class="page-section">
    <h2 class="page-title"><?= sanitize_data($name) ?></h2>
    <?php if (empty($file['client_encrypted'])): ?>
        <p class="page-description">This file was not encrypted in the browser.</p>
        <p><a class="btn btn-primary" href="preview.php?id=<?= (int) $fileId ?>">Open preview</a></p>
    <?php else: ?>
        <p class="page-description">The server stored only the sealed bytes. The passphrase never left your browser, so it cannot be recovered here. Preview stays off.</p>
        <form class="form" id="decryptForm">
            <div class="form-group">
                <label for="passphrase">Passphrase</label>
                <input type="password" id="passphrase" autocomplete="off" required>
            </div>
            <button type="submit" class="btn btn-primary">Decrypt and save</button>
            <a class="btn btn-quiet" href="download.php?id=<?= (int) $fileId ?>">Download sealed file</a>
        </form>
        <p class="form-hint" id="decryptStatus" role="status"></p>
    <?php endif; ?>
</div>
<?php if (!empty($file['client_encrypted'])): ?>
<script src="<?= sanitize_data(app_asset_url('assets/client_encrypt.js')) ?>"></script>
<script>
document.getElementById('decryptForm').addEventListener('submit', function (event) {
    event.preventDefault();
    var status = document.getElementById('decryptStatus');
    var passphrase = document.getElementById('passphrase').value;
    status.textContent = 'Decrypting…';
    fetch('download.php?id=<?= (int) $fileId ?>', { credentials: 'same-origin' })
        .then(function (response) {
            if (!response.ok) throw new Error('Could not read the file. Open the download page if it asks for a password.');
            return response.arrayBuffer();
        })
        .then(function (buffer) {
            return datadockEncrypt.decryptBytes(new Uint8Array(buffer), passphrase);
        })
        .then(function (plain) {
            var blob = new Blob([plain]);
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = <?= json_encode($name) ?>;
            link.click();
            URL.revokeObjectURL(link.href);
            status.textContent = 'Saved the decrypted file. The passphrase was not sent to the server.';
        })
        .catch(function (error) {
            status.textContent = error && error.message ? error.message : 'Could not decrypt that file. Check the passphrase.';
        });
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
