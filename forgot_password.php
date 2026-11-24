<?php
require_once __DIR__ . '/includes/auth.php';
init_session();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    datadock_reject_invalid_csrf('forgot_password.php');
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'][] = "❌ Please enter a valid email address.";
    } else {
        // SECURITY: Do NOT create or display a password-reset token for an
        // unauthenticated requester. This build has no email delivery
        // (see includes/email_verify.php: "SMTP is not wired up yet"), so the
        // previous behaviour printed a working reset link on screen to ANYONE who
        // submitted a known email address. That let an unauthenticated attacker
        // reset any account's password — including admin — and fully take it over.
        //
        // Self-service reset links must be delivered out-of-band (email) so that
        // only the mailbox owner receives them. Until SMTP exists, resets are
        // admin-initiated: Admin Panel -> User Management -> "Copy reset link"
        // (admin_sections/admin_generate_password_reset.php, which is require_admin()
        // gated). A uniform response is returned here regardless of whether the
        // account exists, which also removes the account/email enumeration oracle.
        $submitted = true;
    }
}

$pageTitle = "Forgot password";
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section auth-form">
    <h2 class="page-title">Forgot password</h2>
    <p class="page-description">Enter the email on your account. If it matches an account, an administrator can issue a one-time reset link for you.</p>

    <?php if ($submitted): ?>
        <div class="flash success persistent" role="status">
            If an account exists for that email, please contact your administrator to have a one-time reset link generated. For security, reset links are never shown on this page.
        </div>
        <p class="form-hint"><a href="login.php">Back to login</a></p>
    <?php else: ?>
        <form method="post" class="form">
            <?= datadock_csrf_field() ?>
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" name="email" id="email" required>
            </div>
            <button type="submit" class="btn btn-primary">Request reset</button>
        </form>
        <p class="form-hint"><a href="login.php">Back to login</a></p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
