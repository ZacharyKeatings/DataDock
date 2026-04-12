<?php
// Load settings early for debug mode and maintenance check
require_once __DIR__ . '/settings_loader.php';
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

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Initializes the session and applies configurable timeout.
 * Loads settings to get session_timeout_minutes; 0 = until browser close.
 */
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        if (file_exists(__DIR__ . '/../config/settings.php')) {
            $settings = datadock_load_settings();
            $timeoutMinutes = (int) ($settings['session_timeout_minutes'] ?? 60);
            if ($timeoutMinutes > 0) {
                $lifetime = $timeoutMinutes * 60;
                ini_set('session.gc_maxlifetime', (string) $lifetime);
                session_set_cookie_params([
                    'lifetime' => $lifetime,
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
        }
        session_start();
    }

    // Maintenance mode: block non-admins (except on login.php so admins can log in)
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
        }
    }
}

/**
 * Require user to be logged in. Redirect to login page if not.
 */
function require_login() {
    if (empty($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Require user to be an admin. Redirect if not.
 */
function require_admin() {
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: index.php");
        exit;
    }
}
