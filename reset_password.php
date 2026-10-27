<?php
require_once __DIR__ . '/includes/auth.php';
init_session();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();

$tokenParam = trim($_GET['token'] ?? $_POST['token'] ?? '');
$validToken = null;

if ($tokenParam !== '') {
    $stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE token = ? AND expires_at > UTC_TIMESTAMP()");
    $stmt->execute([$tokenParam]);
    $validToken = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < 6) {
        $_SESSION['flash_error'][] = "❌ Password must be at least 6 characters.";
    } elseif ($newPass !== $confirm) {
        $_SESSION['flash_error'][] = "❌ Passwords do not match.";
    } else {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $validToken['user_id']]);
        $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?")->execute([$tokenParam]);
        $_SESSION['flash_success'][] = "✅ Password reset. You can now log in.";
        header("Location: login.php");
        exit;
    }
}

$pageTitle = "Reset password";
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section auth-form">
    <h2 class="page-title">Reset password</h2>

    <?php if (!$validToken): ?>
        <div class="flash warning persistent" role="status">
            This reset link is invalid or has expired. <a href="forgot_password.php">Request a new link</a> or <a href="login.php">log in</a>.
        </div>
    <?php else: ?>
        <p class="page-description">Choose a new password of at least 6 characters.</p>
        <form method="post" class="form" onsubmit="return validateResetForm()">
            <input type="hidden" name="token" value="<?= sanitize_data($tokenParam) ?>">
            <div class="form-group">
                <label for="new_password">New password</label>
                <input type="password" name="new_password" id="new_password" required minlength="6">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm new password</label>
                <input type="password" name="confirm_password" id="confirm_password" required>
            </div>
            <p class="form-hint" id="reset-mismatch" hidden>Passwords do not match.</p>
            <button type="submit" class="btn btn-primary">Set new password</button>
        </form>
        <script>
        function validateResetForm() {
            var password = document.getElementById('new_password');
            var confirm = document.getElementById('confirm_password');
            var hint = document.getElementById('reset-mismatch');
            var mismatch = password.value !== confirm.value;
            if (hint) hint.hidden = !mismatch;
            return !mismatch;
        }
        </script>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
