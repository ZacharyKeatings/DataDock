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

    <?php if ($resetLink !== null): ?>
        <div class="flash success">
            If an account exists for that email, use the link below to set a new password. The link is valid for 1 hour.
        </div>
        <div class="form-group" style="margin-top: 1rem;">
            <label for="reset_url">Reset link</label>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                <input type="text" id="reset_url" readonly value="<?= sanitize_data($resetLink) ?>" style="flex: 1; min-width: 200px;">
                <button type="button" class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('reset_url').value); this.textContent='Copied!'; setTimeout(function(){ this.textContent='Copy'; }.bind(this), 2000);">Copy</button>
            </div>
            <p class="settings-hint">Open this link in your browser to choose a new password. If you did not request this, you can ignore it.</p>
        </div>
        <p><a href="login.php">Back to login</a></p>
    <?php else: ?>
        <p>Enter your account email address. If an account exists, you will receive a one-time reset link to set a new password (no email is sent; the link is shown on this page).</p>
        <form method="post" class="form">
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" name="email" id="email" required>
            </div>
            <button type="submit" class="btn btn-primary">Get reset link</button>
        </form>
        <p style="margin-top: 1rem;"><a href="login.php">Back to login</a></p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
