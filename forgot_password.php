<?php
require_once __DIR__ . '/includes/auth.php';
init_session();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();

$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'][] = "❌ Please enter a valid email address.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = (new DateTime('now', new DateTimeZone('UTC')))->modify('+1 hour')->format('Y-m-d H:i:s');
            $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")->execute([$user['id'], $token, $expiresAt]);
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $scriptName = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
            $resetLink = $baseUrl . $scriptName . '/reset_password.php?token=' . urlencode($token);
        } else {
            $_SESSION['flash_warning'][] = "If an account exists for that email, a reset link would appear below. Please check the address or contact an administrator.";
        }
    }
}

$pageTitle = "Forgot password";
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section auth-form">
    <h2 class="page-title">Forgot password</h2>
    <p class="page-description">Enter the email on your account. If it matches, a one-time reset link is shown here. Nothing is emailed.</p>

    <?php if ($resetLink !== null): ?>
        <div class="flash success persistent" role="status">
            Use the link below within 1 hour to choose a new password.
        </div>
        <div class="form-group">
            <label for="reset_url">Reset link</label>
            <div class="onetimelink-box">
                <input type="text" id="reset_url" class="onetimelink-input" readonly value="<?= sanitize_data($resetLink) ?>">
                <button type="button" class="btn btn-primary btn-small btn-copy" data-copy="<?= sanitize_data($resetLink) ?>">Copy</button>
            </div>
            <p class="form-hint">If you did not request this, you can ignore the link.</p>
        </div>
        <p class="form-hint"><a href="login.php">Back to login</a></p>
    <?php else: ?>
        <form method="post" class="form">
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" name="email" id="email" required>
            </div>
            <button type="submit" class="btn btn-primary">Get reset link</button>
        </form>
        <p class="form-hint"><a href="login.php">Back to login</a></p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
