<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/settings.php';

$errors = [];
$input = '';

// Brute force config
$bruteForce = $settings['brute_force'] ?? [];
$bruteEnabled    = $bruteForce['enabled'] ?? true;
$maxAttempts     = $bruteForce['max_attempts'] ?? 5;
$lockoutMinutes  = $bruteForce['lockout_minutes'] ?? 15;
$lockoutWindow   = $bruteForce['lockout_window'] ?? 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = trim($_POST['input'] ?? '');
    $password = $_POST['password'] ?? '';
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $windowStart = $now->modify("-$lockoutWindow minutes")->format('Y-m-d H:i:s');
    $now = new DateTime('now', new DateTimeZone('UTC')); // reset $now

    if (empty($input) || empty($password)) {
        $errors[] = "Both fields are required.";
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$input, $input]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $userId = $user['id'];

            if ($bruteEnabled) {
                // Check for lockout
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE user_id = ? AND success = 0 AND attempted_at > ?");
                $stmt->execute([$userId, $windowStart]);
                $failedAttempts = $stmt->fetchColumn();

                if ($failedAttempts >= $maxAttempts) {
                    $latest = $pdo->prepare("SELECT MAX(attempted_at) FROM login_attempts WHERE user_id = ?");
                    $latest->execute([$userId]);
                    $lastAttemptTime = new DateTime($latest->fetchColumn(), new DateTimeZone('UTC'));
                    $lockoutUntil = $lastAttemptTime->modify("+$lockoutMinutes minutes");

                    if ($now < $lockoutUntil) {
                        $remaining = $lockoutUntil->getTimestamp() - $now->getTimestamp();
                        $errors[] = "Too many failed login attempts. Please try again in " . ceil($remaining / 60) . " minutes.";
                    } else {
                        // Lockout expired, clear old attempts
                        $pdo->prepare("DELETE FROM login_attempts WHERE user_id = ?")->execute([$userId]);
                    }
                }
            }

            // Proceed only if no errors yet
            if (empty($errors)) {
                if (password_verify($password, $user['password_hash'])) {
                    // Success – delete old attempts
                    if ($bruteEnabled) {
                        $pdo->prepare("DELETE FROM login_attempts WHERE user_id = ?")->execute([$userId]);
                    }

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    header("Location: dashboard.php");
                    exit;
                } else {
                    // Log failed attempt
                    if ($bruteEnabled) {
                        $stmt = $pdo->prepare("INSERT INTO login_attempts (user_id, success, attempted_at) VALUES (?, 0, UTC_TIMESTAMP())");
                        $stmt->execute([$userId]);
                    }
                    $errors[] = "Invalid credentials.";
                }
            }
        } else {
            $errors[] = "Invalid credentials.";

            // Anonymous attempt tracking (if brute force enabled)
            if ($bruteEnabled) {
                $anonId = hash('sha256', $input);
                $stmt = $pdo->prepare("INSERT INTO login_attempts (anon_id, success, attempted_at) VALUES (?, 0, UTC_TIMESTAMP())");
                $stmt->execute([$anonId]);
            }
        }
    }
}

$pageTitle = "Login";
require_once __DIR__ . '/includes/header.php';
?>

<h2>Login</h2>

<?php if (!empty($errors)): ?>
    <div class="error">
        <?php foreach ($errors as $e): ?>
            <div>• <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post">
    <label for="input">Username or Email</label>
    <input type="text" name="input" id="input" value="<?= htmlspecialchars($input) ?>" required>

    <label for="password">Password</label>
    <input type="password" name="password" id="password" required>

    <button type="submit">Login</button>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
