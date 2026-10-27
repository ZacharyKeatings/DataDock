<?php
require_once __DIR__ . '/includes/auth.php';
init_session();

if (isset($_GET['local'])) {
    setcookie('datadock_local_login', '1', [
        'expires' => time() + 43200,
        'path' => '/',
        'secure' => function_exists('datadock_request_is_https') && datadock_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['datadock_local_login'] = '1';
}
if (isset($_GET['sso'])) {
    setcookie('datadock_local_login', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => function_exists('datadock_request_is_https') && datadock_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE['datadock_local_login']);
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totp_code'])) {
    $pending = $_SESSION['totp_pending'] ?? null;
    $code = (string) ($_POST['totp_code'] ?? '');
    if (!is_array($pending) || (int) ($pending['expires'] ?? 0) < time()) {
        unset($_SESSION['totp_pending']);
        $_SESSION['flash_error'][] = '❌ That sign-in step expired. Enter your password again.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, role, totp_secret, totp_enabled, suspended FROM users WHERE id = ?');
        $stmt->execute([(int) $pending['user_id']]);
        $totpUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$totpUser || !empty($totpUser['suspended']) || !datadock_totp_verify((string) ($totpUser['totp_secret'] ?? ''), $code)) {
            $_SESSION['flash_error'][] = '❌ That authenticator code is not valid.';
        } else {
            datadock_establish_session($pdo, $totpUser, $settings, !empty($pending['remember']));
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                        if (!empty($user['suspended'])) {
                            $_SESSION['flash_error'][] = '❌ This account is suspended.';
                        } else {
                        $maintenanceMode = $settings['maintenance_mode'] ?? false;
                        if ($maintenanceMode && ($user['role'] ?? 'user') !== 'admin') {
                            $_SESSION['flash_error'][] = "❌ Site is under maintenance. Only admins can log in at this time.";
                        } else {
                            if ($bruteEnabled) {
                                $pdo->prepare("DELETE FROM login_attempts WHERE user_id = ?")->execute([$userId]);
                            }
                            datadock_login_after_password($pdo, $user, $settings, $rememberThisDevice);
                        }
                        }
                    } else {
                        $ldapCfg = datadock_identity_config($settings)['ldap'];
                        $ldapHit = datadock_ldap_authenticate($ldapCfg, (string) $user['username'], (string) $password);
                        if ($ldapHit) {
                            $maintenanceMode = $settings['maintenance_mode'] ?? false;
                            if ($maintenanceMode && $ldapHit['role'] !== 'admin' && ($user['role'] ?? '') !== 'admin') {
                                $_SESSION['flash_error'][] = "❌ Site is under maintenance. Only admins can log in at this time.";
                                $ldapHit = null;
                            }
                        }
                        if ($ldapHit) {
                            if ($bruteEnabled) {
                                $pdo->prepare("DELETE FROM login_attempts WHERE user_id = ?")->execute([$userId]);
                            }
                            $nextRole = $ldapHit['role'];
                            if ($nextRole !== 'admin' && ($user['role'] ?? '') === 'admin') {
                                $others = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND suspended = 0 AND id != " . (int) $userId)->fetchColumn();
                                if ($others < 1) {
                                    $nextRole = 'admin';
                                }
                            }
                            $pdo->prepare('UPDATE users SET role = ?, auth_source = ? WHERE id = ?')->execute([$nextRole, 'ldap', $userId]);
                            $user['role'] = $nextRole;
                            datadock_login_after_password($pdo, $user, $settings, $rememberThisDevice);
                        }
                        if ($bruteEnabled) {
                            $stmt = $pdo->prepare("INSERT INTO login_attempts (user_id, anon_id, ip_address, success, attempted_at) VALUES (?, NULL, ?, 0, UTC_TIMESTAMP())");
                            $stmt->execute([$userId, $clientIp]);
                        }
                        $_SESSION['flash_error'][] = "❌ Invalid credentials.";
                    }
                }
            } else {
                $ldapCfg = datadock_identity_config($settings)['ldap'];
                $ldapHit = datadock_ldap_authenticate($ldapCfg, $input, (string) $password);
                $maintenanceMode = $settings['maintenance_mode'] ?? false;
                if ($ldapHit && $maintenanceMode && $ldapHit['role'] !== 'admin') {
                    $ldapHit = null;
                    $_SESSION['flash_error'][] = "❌ Site is under maintenance. Only admins can log in at this time.";
                }
                if ($ldapHit) {
                    try {
                        $created = datadock_upsert_external_user($pdo, [
                            'sub' => 'ldap:' . strtolower($input),
                            'preferred_username' => $input,
                            'email' => filter_var($input, FILTER_VALIDATE_EMAIL) ? $input : '',
                            'role' => $ldapHit['role'],
                        ], 'ldap');
                        datadock_login_after_password($pdo, $created, $settings, $rememberThisDevice);
                    } catch (RuntimeException $e) {
                        $_SESSION['flash_error'][] = '❌ ' . $e->getMessage();
                    }
                }
                if (empty($_SESSION['flash_error'])) {
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

    <?php
    $identity = datadock_identity_config($settings);
    $totpPending = is_array($_SESSION['totp_pending'] ?? null) && (int) ($_SESSION['totp_pending']['expires'] ?? 0) >= time();
    ?>
    <?php if ($totpPending): ?>
    <form method="post" class="form">
        <p class="page-description">Enter the 6-digit code from your authenticator app.</p>
        <div class="form-group">
            <label for="totp_code">Authenticator code</label>
            <input type="text" name="totp_code" id="totp_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,8}" required>
        </div>
        <button type="submit" class="btn btn-primary">Continue</button>
    </form>
    <?php else: ?>
    <?php if ($identity['oidc']['enabled'] && $identity['oidc']['issuer'] !== ''): ?>
        <p><a class="btn btn-primary" href="oidc.php">Sign in with OpenID Connect</a></p>
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

        <div class="form-group">
            <label for="claim_code">Guest upload claim code <span class="paste-optional">(optional)</span></label>
            <input type="text" name="claim_code" id="claim_code" value="<?= htmlspecialchars((string) ($_POST['claim_code'] ?? '')) ?>" autocomplete="off" spellcheck="false">
        </div>

        <?php if ($rememberDeviceOffer): ?>
        <div class="form-group settings-row-checkbox">
            <label>
                <input type="checkbox" name="remember_device" value="1">
                Remember this device
            </label>
            <p class="form-hint">Keeps you signed in after you close the browser, until you log out or the session goes idle.</p>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Login</button>
        <p class="form-hint"><a href="forgot_password.php">Forgot password?</a></p>
    </form>
    <?php if ($identity['forward_auth']['enabled']): ?>
        <p class="form-hint"><a href="login.php?local=1">Use a local account</a><?php if (!empty($_COOKIE['datadock_local_login'])): ?> · <a href="login.php?sso=1">Use proxy sign-in</a><?php endif; ?></p>
    <?php endif; ?>
    <?php if ($identity['ldap']['enabled'] && !function_exists('ldap_connect')): ?>
        <p class="form-hint">Directory login is configured, and this PHP build does not include the LDAP extension. Local passwords still work.</p>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
