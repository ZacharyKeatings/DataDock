<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
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
        if (strlen($password) < 6) {
            $_SESSION['flash_error'][] = "❌ Password must be at least 6 characters.";
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

        if ($inviteOnly && $signupToken !== '') {
            $pdo->prepare("UPDATE signup_tokens SET used_at = UTC_TIMESTAMP() WHERE token = ?")->execute([$signupToken]);
        }

        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'user';
        datadock_finalize_login_session($settings, false);

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
        <div class="error-box">
            🚫 Registration is currently disabled by the site administrator.
        </div>
    <?php elseif ($inviteOnly && !$showRegistrationForm): ?>
        <div class="error-box">
            🚫 Registration is by invitation only. Please use the signup link provided by an administrator, or <a href="login.php">log in</a> if you already have an account.
        </div>
    <?php else: ?>
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
                <input type="password" name="password" id="password" required>
            </div>

            <div class="form-group">
                <label for="confirm">Confirm Password</label>
                <input type="password" name="confirm" id="confirm" required>
            </div>

            <button type="submit" class="btn btn-primary">Register</button>
        </form>

        <script>
        function validateForm() {
            const password = document.getElementById("password");
            const confirm = document.getElementById("confirm");
            if (password.value !== confirm.value) {
                alert("Passwords do not match.");
                return false;
            }
            return true;
        }
        </script>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
