<?php
require_once __DIR__ . '/includes/auth.php';
init_session();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
$rememberDeviceRow = is_array($settings['remember_device'] ?? null) ? $settings['remember_device'] : [];
$rememberDeviceOffer = !array_key_exists('enabled', $rememberDeviceRow) || !empty($rememberDeviceRow['enabled']);

$input = '';
$bruteForce      = $settings['brute_force'] ?? [];
$bruteEnabled    = $bruteForce['enabled'] ?? true;
$maxAttempts     = $bruteForce['max_attempts'] ?? 5;
$lockoutMinutes  = $bruteForce['lockout_minutes'] ?? 15;
$lockoutWindow   = $bruteForce['lockout_window'] ?? 10;
$adaptiveCooldown = !empty($bruteForce['adaptive_cooldown_enabled']);
$adaptiveWindow  = (int) ($bruteForce['adaptive_cooldown_ip_window_minutes'] ?? 60);
$adaptiveSteps   = $bruteForce['adaptive_cooldown_steps'] ?? [5 => 5, 15 => 15, 30 => 60];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = trim($_POST['input'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberThisDevice = $rememberDeviceOffer && isset($_POST['remember_device']);
    $clientIp = get_client_ip();
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $windowStart = (clone $now)->modify("-$lockoutWindow minutes")->format('Y-m-d H:i:s');
    $now = new DateTime('now', new DateTimeZone('UTC'));

    if (empty($input) || empty($password)) {
        $_SESSION['flash_error'][] = "❌ Both fields are required.";
    } else {
        // Per-IP adaptive cooldown: progressive lockout for repeated failures from one IP (across usernames)
        if ($bruteEnabled && $adaptiveCooldown && $adaptiveWindow > 0) {
            $ipWindowStart = (clone $now)->modify("-$adaptiveWindow minutes")->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempted_at > ?");
            $stmt->execute([$clientIp, $ipWindowStart]);
            $ipFailCount = (int) $stmt->fetchColumn();
            $lockoutMins = $lockoutMinutes;
            krsort($adaptiveSteps, SORT_NUMERIC);
            foreach ($adaptiveSteps as $threshold => $mins) {
                if ($ipFailCount >= $threshold) {
                    $lockoutMins = $mins;
                    break;
                }
            }
            $stmt = $pdo->prepare("SELECT MAX(attempted_at) FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempted_at > ?");
            $stmt->execute([$clientIp, $ipWindowStart]);
            $lastIpAttempt = $stmt->fetchColumn();
            if ($lastIpAttempt && $ipFailCount >= ($maxAttempts ?: 1)) {
                $lockoutUntil = (new DateTime($lastIpAttempt, new DateTimeZone('UTC')))->modify("+$lockoutMins minutes");
                if ($now < $lockoutUntil) {
                    $remaining = $lockoutUntil->getTimestamp() - $now->getTimestamp();
                    $_SESSION['flash_error'][] = "❌ Too many failed login attempts from your network. Try again in " . ceil($remaining / 60) . " minutes.";
                    $now = null;
                }
            }
        }

        if ($now && empty($_SESSION['flash_error'])) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$input, $input]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $userId = $user['id'];

                if ($bruteEnabled) {
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
                            $_SESSION['flash_error'][] = "❌ Too many failed login attempts. Try again in " . ceil($remaining / 60) . " minutes.";
                        } else {
                            $pdo->prepare("DELETE FROM login_attempts WHERE user_id = ?")->execute([$userId]);
                        }
                    }
                }

                if (empty($_SESSION['flash_error'])) {
                    if (password_verify($password, $user['password_hash'])) {
                        $maintenanceMode = $settings['maintenance_mode'] ?? false;
                        if ($maintenanceMode && ($user['role'] ?? 'user') !== 'admin') {
                            $_SESSION['flash_error'][] = "❌ Site is under maintenance. Only admins can log in at this time.";
                        } else {
                            if ($bruteEnabled) {
                                $pdo->prepare("DELETE FROM login_attempts WHERE user_id = ?")->execute([$userId]);
                            }
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['role'] = $user['role'];
                            datadock_finalize_login_session($settings, $rememberThisDevice);
                            $_SESSION['flash_success'][] = "🎉 Login successful. Welcome, " . $user['username'] . "!";
                            header("Location: dashboard.php");
                            exit;
                        }
                    } else {
                        if ($bruteEnabled) {
                            $stmt = $pdo->prepare("INSERT INTO login_attempts (user_id, anon_id, ip_address, success, attempted_at) VALUES (?, NULL, ?, 0, UTC_TIMESTAMP())");
                            $stmt->execute([$userId, $clientIp]);
                        }
                        $_SESSION['flash_error'][] = "❌ Invalid credentials.";
                    }
                }
            } else {
                $_SESSION['flash_error'][] = "❌ Invalid credentials.";
                if ($bruteEnabled) {
                    $anonId = hash('sha256', $input);
                    $stmt = $pdo->prepare("INSERT INTO login_attempts (user_id, anon_id, ip_address, success, attempted_at) VALUES (NULL, ?, ?, 0, UTC_TIMESTAMP())");
                    $stmt->execute([$anonId, $clientIp]);
                }
            }
        }
    }
}

$pageTitle = "Login";
$idleNotice = isset($_GET['reason']) && $_GET['reason'] === 'idle';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section auth-form">
    <h2 class="page-title">Login</h2>

    <?php if ($idleNotice): ?>
        <div class="flash warning" role="status">
            Your session expired due to inactivity. Please sign in again.
        </div>
    <?php endif; ?>

    <form method="post" class="form">
        <div class="form-group">
            <label for="input">Username or Email</label>
            <input type="text" name="input" id="input" value="<?= htmlspecialchars($input) ?>" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>
        </div>

        <?php if ($rememberDeviceOffer): ?>
        <div class="form-group settings-row-checkbox">
            <label>
                <input type="checkbox" name="remember_device" value="1">
                Remember this device (keeps you signed in across browser restarts until idle timeout or logout; not a separate login token)
            </label>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Login</button>
        <p style="margin-top: 1rem;"><a href="forgot_password.php">Forgot password?</a></p>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
