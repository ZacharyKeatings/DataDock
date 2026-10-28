<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
require_once __DIR__ . '/includes/accounts.php';
$settings = datadock_load_settings();
require_once __DIR__ . '/includes/read_only.php';
if (datadock_read_only_enabled($settings)) {
    $_SESSION['flash_error'][] = '❌ New account registration is disabled (read-only / archive mode).';
    header('Location: login.php');
    exit;
}

$username = '';
$email = '';
$inviteOnly = !empty($settings['invite_only_registration']);

/**
 * Validate signup token: exists, not expired, not used.
 * @return array|null Row if valid, null otherwise
 */
function get_valid_signup_token(PDO $pdo, string $token): ?array {
    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM signup_tokens WHERE token = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// Handle registration before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $signupToken = trim($_POST['signup_token'] ?? '');
    if ($inviteOnly) {
        if (get_valid_signup_token($pdo, $signupToken) === null) {
            $_SESSION['flash_error'][] = "❌ Invalid, expired, or already used signup link. Please request a new one from an administrator.";
        }
    }

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (empty($_SESSION['flash_error'])) {
        if (strlen($username) < 3) {
            $_SESSION['flash_error'][] = "❌ Username must be at least 3 characters.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'][] = "❌ Invalid email address.";
        }
        $passwordError = datadock_password_error($password, $settings);
        if ($passwordError !== null) {
            $_SESSION['flash_error'][] = "❌ " . $passwordError;
        }
        if ($password !== $confirm) {
            $_SESSION['flash_error'][] = "❌ Passwords do not match.";
        }
    }

    if (empty($_SESSION['flash_error'])) {
        $enforceUniqueEmail = $settings['enforce_unique_email'] ?? true;
        if ($enforceUniqueEmail) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
        }
        if ($stmt->fetch()) {
            $_SESSION['flash_error'][] = $enforceUniqueEmail ? "❌ Username or email already exists." : "❌ Username already exists.";
        }
    }

    if (empty($_SESSION['flash_error'])) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $passwordHash]);
        $newUserId = (int) $pdo->lastInsertId();

        if ($inviteOnly && $signupToken !== '') {
            $pdo->prepare("UPDATE signup_tokens SET used_at = UTC_TIMESTAMP() WHERE token = ?")->execute([$signupToken]);
        }

        require_once __DIR__ . '/includes/email_verify.php';
        if (datadock_email_verification_enabled($settings)) {
            $verify = datadock_begin_email_verification($pdo, $newUserId, $email);
            if ($verify['sent']) {
                $_SESSION['flash_success'][] = 'Account created. Check your email for a confirmation link before signing in.';
            } else {
                $safeUrl = sanitize_data($verify['url']);
                $_SESSION['flash_success'][] = [
                    'html' => true,
                    'msg' => 'Account created. Outbound email is not configured yet, so confirm this address with the link below. It expires in two days.<br><a href="' . $safeUrl . '">Confirm email</a>',
                ];
            }
            header('Location: login.php');
            exit;
        }

        $_SESSION['user_id'] = $newUserId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'user';
        datadock_finalize_login_session($settings, false);
        datadock_session_record($pdo, (int) $_SESSION['user_id']);
        $claimCode = trim((string) ($_POST['claim_code'] ?? ''));
        if ($claimCode !== '') {
            require_once __DIR__ . '/includes/guest_claim.php';
            $claimed = datadock_claim_guest_uploads($pdo, $settings, (int) $_SESSION['user_id'], $claimCode);
            if ($claimed['message'] !== '') {
                if ($claimed['ok']) {
                    $_SESSION['flash_success'][] = $claimed['message'];
                } else {
                    $_SESSION['flash_error'][] = $claimed['message'];
                }
            }
        }

        $_SESSION['flash_success'][] = "🎉 Registration successful. Welcome, $username!";

        header("Location: dashboard.php");
        exit;
    }
}

// For GET: when invite-only, require valid token to show form
$signupTokenParam = trim($_GET['token'] ?? '');
$showRegistrationForm = true;
if ($inviteOnly && !empty($settings['registration_enabled'])) {
    if ($signupTokenParam === '' || get_valid_signup_token($pdo, $signupTokenParam) === null) {
        $showRegistrationForm = false;
    }
}

$pageTitle = "Register";
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section auth-form">
    <h2 class="page-title">Register</h2>

    <?php if (empty($settings['registration_enabled']) || !$settings['registration_enabled']): ?>
        <div class="flash warning persistent" role="status">
            Registration is turned off. <a href="login.php">Log in</a> if you already have an account.
        </div>
    <?php elseif ($inviteOnly && !$showRegistrationForm): ?>
        <div class="flash warning persistent" role="status">
            Registration is by invitation only. Use the signup link from an administrator, or <a href="login.php">log in</a> if you already have an account.
        </div>
    <?php else: ?>
        <p class="page-description">Create an account to upload and manage files.</p>
        <form method="post" class="form" onsubmit="return validateForm()">
            <?php if ($inviteOnly && $signupTokenParam !== ''): ?>
            <input type="hidden" name="signup_token" value="<?= sanitize_data($signupTokenParam) ?>">
            <?php endif; ?>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" value="<?= sanitize_data($username) ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="<?= sanitize_data($email) ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required minlength="<?= (int) datadock_password_policy($settings)['min_length'] ?>">
                <p class="form-hint"><?= sanitize_data(datadock_password_hint($settings)) ?></p>
            </div>

            <div class="form-group">
                <label for="confirm">Confirm Password</label>
                <input type="password" name="confirm" id="confirm" required>
            </div>

            <div class="form-group">
                <label for="claim_code">Guest upload claim code <span class="paste-optional">(optional)</span></label>
                <input type="text" name="claim_code" id="claim_code" value="<?= sanitize_data($_POST['claim_code'] ?? '') ?>" autocomplete="off" spellcheck="false">
            </div>

            <p class="form-hint" id="register-mismatch" hidden>Passwords do not match.</p>
            <button type="submit" class="btn btn-primary">Register</button>
        </form>
        <p class="form-hint"><a href="login.php">Already have an account? Log in</a></p>

        <script>
        function validateForm() {
            var password = document.getElementById("password");
            var confirm = document.getElementById("confirm");
            var hint = document.getElementById("register-mismatch");
            var mismatch = password.value !== confirm.value;
            if (hint) hint.hidden = !mismatch;
            return !mismatch;
        }
        </script>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
