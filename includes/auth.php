<?php
// Load settings early for debug mode and maintenance check
require_once __DIR__ . '/settings_loader.php';
require_once __DIR__ . '/security_headers.php';

$settingsPath = __DIR__ . '/../config/settings.php';
if (file_exists($settingsPath)) {
    $settings = datadock_load_settings();
    $debugMode = $settings['debug_mode'] ?? false;
    if ($debugMode) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    } else {
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
        ini_set('display_errors', '0');
    }
}

/**
 * Initializes the session and applies configurable timeout.
 * Loads settings to get session_timeout_minutes; 0 = until browser close (no idle limit).
 */
function init_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $settings = file_exists(__DIR__ . '/../config/settings.php') ? datadock_load_settings() : [];
        $rememberCfg = is_array($settings['remember_device'] ?? null) ? $settings['remember_device'] : [];
        $rememberAllowed = !empty($rememberCfg['enabled']);
        $rememberDays = (int) ($rememberCfg['cookie_days'] ?? 30);
        $rememberDays = max(1, min(365, $rememberDays));
        $rememberMaxSec = $rememberAllowed ? $rememberDays * 86400 : 0;
        datadock_session_post_start_maintenance_and_idle();
        datadock_session_refresh_remember_cookie($settings, $rememberAllowed, $rememberMaxSec, datadock_request_is_https());
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $settings = file_exists(__DIR__ . '/../config/settings.php') ? datadock_load_settings() : [];
    $timeoutMinutes = (int) ($settings['session_timeout_minutes'] ?? 60);
    $rememberCfg = is_array($settings['remember_device'] ?? null) ? $settings['remember_device'] : [];
    $rememberAllowed = !empty($rememberCfg['enabled']);
    $rememberDays = (int) ($rememberCfg['cookie_days'] ?? 30);
    if ($rememberDays < 1) {
        $rememberDays = 1;
    }
    if ($rememberDays > 365) {
        $rememberDays = 365;
    }
    $rememberMaxSec = $rememberAllowed ? $rememberDays * 86400 : 0;

    $timeoutSec = $timeoutMinutes > 0 ? $timeoutMinutes * 60 : 0;
    $serverDefaultGc = (int) ini_get('session.gc_maxlifetime');
    if ($serverDefaultGc < 60) {
        $serverDefaultGc = 1440 * 60;
    }
    $gcLifetime = max($timeoutSec, $rememberMaxSec, $serverDefaultGc);
    ini_set('session.gc_maxlifetime', (string) $gcLifetime);

    $secure = datadock_request_is_https();
    $cookieLifetime = $timeoutMinutes > 0 ? $timeoutSec : 0;

    session_set_cookie_params([
        'lifetime' => $cookieLifetime,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    datadock_session_post_start_maintenance_and_idle();
    datadock_session_refresh_remember_cookie($settings, $rememberAllowed, $rememberMaxSec, $secure);
}

/**
 * After successful authentication, rotate the session id and apply optional "remember this device"
 * (longer session cookie only; same PHP session, no separate token store).
 */
function datadock_finalize_login_session(array $settings, bool $rememberDevice): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $rememberCfg = is_array($settings['remember_device'] ?? null) ? $settings['remember_device'] : [];
    $rememberAllowed = !empty($rememberCfg['enabled']);
    $rememberDays = (int) ($rememberCfg['cookie_days'] ?? 30);
    if ($rememberDays < 1) {
        $rememberDays = 1;
    }
    if ($rememberDays > 365) {
        $rememberDays = 365;
    }
    $rememberMaxSec = $rememberAllowed ? $rememberDays * 86400 : 0;

    $_SESSION['datadock_last_activity'] = time();
    $_SESSION['datadock_remember_device'] = $rememberAllowed && $rememberDevice;

    $secure = datadock_request_is_https();
    datadock_session_refresh_remember_cookie($settings, $rememberAllowed, $rememberMaxSec, $secure);
}

function datadock_session_refresh_remember_cookie(array $settings, bool $rememberAllowed, int $rememberMaxSec, bool $secure): void {
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['user_id'])) {
        return;
    }
    if (!$rememberAllowed || empty($_SESSION['datadock_remember_device']) || $rememberMaxSec <= 0) {
        return;
    }
    $params = session_get_cookie_params();
    $expires = time() + $rememberMaxSec;
    setcookie(session_name(), session_id(), [
        'expires' => $expires,
        'path' => $params['path'] !== '' ? $params['path'] : '/',
        'domain' => $params['domain'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function datadock_session_pdo(): ?PDO {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }
    $configDb = __DIR__ . '/../config/db.php';
    if (!is_readable($configDb)) {
        return null;
    }
    $loaded = (static function (string $configDb): ?PDO {
        require $configDb;
        return (isset($pdo) && $pdo instanceof PDO) ? $pdo : null;
    })($configDb);
    if ($loaded instanceof PDO) {
        $GLOBALS['pdo'] = $loaded;
    }
    return $loaded instanceof PDO ? $loaded : null;
}

/**
 * Reload username and role from the database so a demotion or deletion
 * takes effect on the next request instead of lingering in the session.
 */
function datadock_sync_session_user(): void {
    static $done = false;
    if ($done || session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['user_id'])) {
        return;
    }
    $pdo = datadock_session_pdo();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $stmt = $pdo->prepare('SELECT username, role FROM users WHERE id = ?');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return;
    }
    $done = true;
    if (!$row) {
        unset(
            $_SESSION['user_id'],
            $_SESSION['username'],
            $_SESSION['role'],
            $_SESSION['datadock_remember_device']
        );
        return;
    }
    $_SESSION['role'] = (($row['role'] ?? '') === 'admin') ? 'admin' : 'user';
    if (!empty($row['username']) && is_string($row['username'])) {
        $_SESSION['username'] = $row['username'];
    }
}

function datadock_csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    $token = $_SESSION['csrf_token'] ?? '';
    if (!is_string($token) || strlen($token) !== 64) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
    }
    return $token;
}

function datadock_csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(datadock_csrf_token(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function datadock_csrf_is_valid(): bool {
    $sent = $_POST['csrf_token'] ?? '';
    $sess = $_SESSION['csrf_token'] ?? '';
    return is_string($sent) && is_string($sess) && $sess !== '' && hash_equals($sess, $sent);
}

function datadock_reject_invalid_csrf(string $redirect): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && datadock_csrf_is_valid()) {
        return;
    }
    $_SESSION['flash_error'][] = '❌ That action could not be confirmed. Use the button on the page.';
    header('Location: ' . $redirect);
    exit;
}

function datadock_session_post_start_maintenance_and_idle(): void {
    datadock_sync_session_user();
    $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');

    if ($currentScript !== 'login.php' && $currentScript !== 'maintenance.php') {
        if (file_exists(__DIR__ . '/../config/settings.php')) {
            $settings = datadock_load_settings();
            $maintenanceMode = $settings['maintenance_mode'] ?? false;
            if ($maintenanceMode) {
                $isAdmin = !empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin';
                if (!$isAdmin) {
                    require __DIR__ . '/../maintenance.php';
                    exit;
                }
            }

            $timeoutMinutes = (int) ($settings['session_timeout_minutes'] ?? 60);
            if ($timeoutMinutes > 0 && !empty($_SESSION['user_id'])) {
                $timeoutSec = $timeoutMinutes * 60;
                $now = time();
                $last = (int) ($_SESSION['datadock_last_activity'] ?? $now);
                if ($last > 0 && ($now - $last) > $timeoutSec) {
                    $_SESSION = [];
                    if (ini_get('session.use_cookies')) {
                        $p = session_get_cookie_params();
                        setcookie(session_name(), '', [
                            'expires' => $now - 3600,
                            'path' => $p['path'] !== '' ? $p['path'] : '/',
                            'domain' => $p['domain'] ?? '',
                            'secure' => datadock_request_is_https(),
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]);
                    }
                    session_destroy();
                    header('Location: login.php?reason=idle');
                    exit;
                }
            }

            if (!empty($_SESSION['user_id'])) {
                $_SESSION['datadock_last_activity'] = time();
            }
        }
    }
}

/**
 * Require user to be logged in. Redirect to login page if not.
 */
function require_login(): void {
    datadock_sync_session_user();
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Require user to be an admin. Redirect if not.
 */
function require_admin(): void {
    datadock_sync_session_user();
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header('Location: index.php');
        exit;
    }
}
