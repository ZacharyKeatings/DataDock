<?php
require_once __DIR__ . '/../includes/auth.php';
init_session();
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings_loader.php';
$settings = datadock_load_settings();
require_once __DIR__ . '/../includes/read_only.php';
if (datadock_read_only_enabled($settings)) {
    $_SESSION['flash_error'][] = '❌ Read-only mode: new signup tokens cannot be created.';
    header('Location: ../admin.php?section=users');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin.php?section=users");
    exit;
}

$token = bin2hex(random_bytes(32));
$expiresAt = (new DateTime('now', new DateTimeZone('UTC')))->modify('+7 days')->format('Y-m-d H:i:s');
$stmt = $pdo->prepare("INSERT INTO signup_tokens (token, created_by_user_id, expires_at) VALUES (?, ?, ?)");
$stmt->execute([$token, $_SESSION['user_id'], $expiresAt]);

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');
if (strpos($scriptName, 'admin_sections') !== false) {
    $scriptName = dirname($scriptName);
}
$scriptName = rtrim($scriptName, '/');
$signupUrl = $baseUrl . $scriptName . '/register.php?token=' . urlencode($token);

$_SESSION['flash_success'][] = [
    'msg' => 'Signup link created (valid 7 days, single use). Share it with the new user.'
        . '<div class="onetimelink-box"><input type="text" readonly class="onetimelink-input" value="' . htmlspecialchars($signupUrl) . '">'
        . '<button type="button" class="btn btn-primary btn-small btn-copy" data-copy="' . htmlspecialchars($signupUrl) . '">Copy</button></div>',
    'html' => true
];
header("Location: ../admin.php?section=users");
exit;
