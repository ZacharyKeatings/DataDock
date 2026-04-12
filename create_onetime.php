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
require_once __DIR__ . '/includes/audit_log.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_error'][] = "❌ Invalid file.";
    header("Location: dashboard.php");
    exit;
}

$fileId = (int) $_GET['id'];
$userId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$fileId]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ? AND deleted_at IS NULL AND (quarantine_status = 'approved' OR quarantine_status IS NULL)");
    $stmt->execute([$fileId, $userId]);
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

$linkExpiryOptions = [
    '1_hour' => '+1 hour',
    '1_day' => '+1 day',
    '7_days' => '+7 days',
    '30_days' => '+30 days',
    'never' => null,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $linkKey = $_POST['link_expires'] ?? '1_day';
    if (!isset($linkExpiryOptions[$linkKey])) {
        $linkKey = '1_day';
    }
    $maxUses = (int) ($_POST['max_uses'] ?? 1);
    if ($maxUses < 1) {
        $maxUses = 1;
    }
    if ($maxUses > 100000) {
        $maxUses = 100000;
    }
    $expiresAt = null;
    if ($linkExpiryOptions[$linkKey] !== null) {
        $nowUTC = new DateTime('now', new DateTimeZone('UTC'));
        $expiresAt = (clone $nowUTC)->modify($linkExpiryOptions[$linkKey])->format('Y-m-d H:i:s');
    }

    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("INSERT INTO download_tokens (file_id, token, expires_at, max_uses, use_count) VALUES (?, ?, ?, ?, 0)");
    $stmt->execute([$fileId, $token, $expiresAt, $maxUses]);

    datadock_log_activity($pdo, 'onetime_link_created', [
        'actor_user_id' => $userId,
        'file_id' => $fileId,
        'detail' => ['name' => $file['original_name'] ?? $file['filename'], 'max_uses' => $maxUses, 'expires' => $expiresAt],
    ]);

    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
    $oneTimeUrl = $baseUrl . rtrim($scriptDir, '/') . '/download.php?token=' . $token;

    $pageTitle = "Share link";
    require_once __DIR__ . '/includes/header.php';
    ?>

<div class="page-section">
    <h2 class="page-title">Share link</h2>
    <p>Share this link. It expires after the configured number of downloads and/or when the time window ends (whichever comes first).</p>
    <p><strong>File:</strong> <?= sanitize_data($file['original_name']) ?></p>
    <ul class="form-hint" style="margin-bottom:1rem;">
        <li><strong>Max downloads:</strong> <?= (int) $maxUses ?></li>
        <li><strong>Link expiry:</strong> <?= $expiresAt ? '<span class="utc-datetime" data-utc="' . sanitize_data($expiresAt) . '"></span>' : 'No time limit' ?></li>
    </ul>
    <div class="onetimelink-box">
        <input type="text" id="oneTimeUrl" value="<?= sanitize_data($oneTimeUrl) ?>" readonly class="onetimelink-input">
        <button type="button" class="btn btn-small" onclick="navigator.clipboard.writeText(document.getElementById('oneTimeUrl').value); this.textContent='Copied!'; setTimeout(()=>this.textContent='Copy', 2000)">Copy</button>
    </div>
    <h3 style="margin-top:1.5rem;">QR Code</h3>
    <p>Scan to download (uses the link above):</p>
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&amp;data=<?= urlencode($oneTimeUrl) ?>" alt="QR Code" class="qr-image">
    <p style="margin-top:1.5rem;"><a href="dashboard.php" class="btn btn-primary">Back to Your Files</a></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.utc-datetime').forEach(el => {
        const utc = el.dataset.utc;
        if (utc) {
            const local = new Date(utc + ' UTC');
            el.textContent = local.toLocaleString();
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = "Create share link";
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section">
    <h2 class="page-title">Create share link</h2>
    <p>Generate a link that works for a limited number of downloads and/or until a time limit. Optional file password and IP rules from <strong>Edit file</strong> still apply.</p>
    <p><strong>File:</strong> <?= sanitize_data($file['original_name']) ?></p>
    <form method="post" action="create_onetime.php?id=<?= (int) $fileId ?>" class="settings-card" style="max-width:28rem;">
        <div class="settings-card-body">
            <div class="form-group">
                <label for="link_expires">Link expires (time)</label>
                <select name="link_expires" id="link_expires">
                    <?php foreach ($linkExpiryOptions as $k => $_v): ?>
                        <option value="<?= sanitize_data($k) ?>"<?= $k === '1_day' ? ' selected' : '' ?>><?= ucwords(str_replace('_', ' ', $k)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="max_uses">Max downloads</label>
                <input type="number" name="max_uses" id="max_uses" value="1" min="1" max="100000" required>
                <small class="form-hint">The link stops working after this many successful downloads (each download increments the counter).</small>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create link</button>
                <a href="dashboard.php" class="btn btn-small">Cancel</a>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
