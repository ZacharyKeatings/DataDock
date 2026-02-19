<?php
require_once __DIR__ . '/../includes/auth.php';
init_session();
require_admin();
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['user_id'])) {
    header("Location: ../admin.php?section=users");
    exit;
}

$targetUserId = (int) $_POST['user_id'];
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
$stmt->execute([$targetUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    $_SESSION['flash_error'][] = 'User not found.';
    header("Location: ../admin.php?section=users");
    exit;
}

$token = bin2hex(random_bytes(32));
$expiresAt = (new DateTime('now', new DateTimeZone('UTC')))->modify('+1 hour')->format('Y-m-d H:i:s');
$pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")->execute([$targetUserId, $token, $expiresAt]);

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');
if (strpos($scriptName, 'admin_sections') !== false) {
    $scriptName = dirname($scriptName);
}
$scriptName = rtrim($scriptName, '/');
$resetUrl = $baseUrl . $scriptName . '/reset_password.php?token=' . urlencode($token);

$_SESSION['flash_success'][] = [
    'msg' => 'Password reset link for <strong>' . htmlspecialchars($user['username']) . '</strong> (valid 1 hour):<br><input type="text" readonly class="signup-link-copy" value="' . htmlspecialchars($resetUrl) . '" style="width:100%;max-width:400px;padding:0.25rem;"><button type="button" class="btn btn-primary" onclick="navigator.clipboard.writeText(this.previousElementSibling.value);this.textContent=\'Copied!\';">Copy</button>',
    'html' => true
];
header("Location: ../admin.php?section=users");
exit;
