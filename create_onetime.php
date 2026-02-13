<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_error'][] = "❌ Invalid file.";
    header("Location: dashboard.php");
    exit;
}

$fileId = (int) $_GET['id'];
$userId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
    $stmt->execute([$fileId, $userId]);
}
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    $_SESSION['flash_error'][] = "❌ File not found or permission denied.";
    header("Location: dashboard.php");
    exit;
}

$path = __DIR__ . '/uploads/' . $file['filename'];
if (!file_exists($path)) {
    $_SESSION['flash_error'][] = "❌ File is missing from server.";
    header("Location: dashboard.php");
    exit;
}

$token = bin2hex(random_bytes(32));
$stmt = $pdo->prepare("INSERT INTO download_tokens (file_id, token) VALUES (?, ?)");
$stmt->execute([$fileId, $token]);

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
$oneTimeUrl = $baseUrl . rtrim($scriptDir, '/') . '/download.php?token=' . $token;

$pageTitle = "One-Time Download Link";
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section">
    <h2 class="page-title">One-Time Download Link</h2>
    <p>Share this link. It will work <strong>once</strong> and then expire.</p>
    <p><strong>File:</strong> <?= sanitize_data($file['original_name']) ?></p>
    <div class="onetimelink-box">
        <input type="text" id="oneTimeUrl" value="<?= sanitize_data($oneTimeUrl) ?>" readonly class="onetimelink-input">
        <button type="button" class="btn btn-small" onclick="navigator.clipboard.writeText(document.getElementById('oneTimeUrl').value); this.textContent='Copied!'; setTimeout(()=>this.textContent='Copy', 2000)">Copy</button>
    </div>
    <h3 style="margin-top:1.5rem;">QR Code</h3>
    <p>Scan to download (uses the one-time link above):</p>
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&amp;data=<?= urlencode($oneTimeUrl) ?>" alt="QR Code" class="qr-image">
    <p style="margin-top:1.5rem;"><a href="dashboard.php" class="btn btn-primary">Back to Your Files</a></p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
