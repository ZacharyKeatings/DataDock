<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/access_control.php';
require_once __DIR__ . '/includes/audit_log.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_error'][] = '❌ Invalid file.';
    header('Location: dashboard.php');
    exit;
}

$fileId = (int) $_GET['id'];
$userId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

if ($isAdmin) {
    $stmt = $pdo->prepare('SELECT * FROM files WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$fileId]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM files WHERE id = ? AND user_id = ? AND deleted_at IS NULL AND (quarantine_status = \'approved\' OR quarantine_status IS NULL)');
    $stmt->execute([$fileId, $userId]);
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

$durations = [
    '15_minutes' => 900,
    '1_hour' => 3600,
    '1_day' => 86400,
    '7_days' => 604800,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $k = $_POST['signed_duration'] ?? '1_day';
    if (!isset($durations[$k])) {
        $k = '1_day';
    }
    $exp = time() + $durations[$k];
    $sig = datadock_signed_download_build($pdo, $fileId, $exp);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
    $signedUrl = $baseUrl . rtrim($scriptDir, '/') . '/download.php?id=' . $fileId . '&exp=' . $exp . '&sig=' . rawurlencode($sig);

    datadock_log_activity($pdo, 'signed_link_created', [
        'actor_user_id' => $userId,
        'file_id' => $fileId,
        'detail' => ['name' => $file['original_name'] ?? $file['filename'], 'exp' => $exp],
    ]);

    $pageTitle = 'Signed download link';
    require_once __DIR__ . '/includes/header.php';
    ?>

<div class="page-section">
    <h2 class="page-title">Signed download link</h2>
    <p>Anyone with this URL can download the file until it expires. IP restrictions from <strong>Edit file</strong> still apply. This link bypasses the optional download password (the URL itself is the secret).</p>
    <p><strong>File:</strong> <?= sanitize_data($file['original_name']) ?></p>
    <p class="form-hint">Expires (UTC): <span class="utc-datetime" data-utc="<?= gmdate('Y-m-d H:i:s', $exp) ?>"></span></p>
    <div class="onetimelink-box">
        <input type="text" id="signedUrl" value="<?= sanitize_data($signedUrl) ?>" readonly class="onetimelink-input">
        <button type="button" class="btn btn-small" onclick="navigator.clipboard.writeText(document.getElementById('signedUrl').value); this.textContent='Copied!'; setTimeout(()=>this.textContent='Copy', 2000)">Copy</button>
    </div>
    <p style="margin-top:1.5rem;"><a href="dashboard.php" class="btn btn-primary">Back to Your Files</a></p>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.utc-datetime').forEach(el => {
        const utc = el.dataset.utc;
        if (utc) {
            el.textContent = new Date(utc + ' UTC').toLocaleString();
        }
    });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = 'Create signed link';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section">
    <h2 class="page-title">Create signed link</h2>
    <p class="page-description">Time-limited URL validated with HMAC (no extra database row). Optional IP allowlist on the file still applies.</p>
    <p><strong>File:</strong> <?= sanitize_data($file['original_name']) ?></p>
    <form method="post" action="create_signed.php?id=<?= (int) $fileId ?>" class="settings-card" style="max-width:28rem;">
        <div class="settings-card-body">
            <div class="form-group">
                <label for="signed_duration">Link valid for</label>
                <select name="signed_duration" id="signed_duration">
                    <?php foreach ($durations as $key => $_sec): ?>
                        <option value="<?= sanitize_data($key) ?>"<?= $key === '1_day' ? ' selected' : '' ?>><?= ucwords(str_replace('_', ' ', $key)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Generate link</button>
                <a href="dashboard.php" class="btn btn-small">Cancel</a>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
