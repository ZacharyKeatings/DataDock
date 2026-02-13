<?php
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
        $settingsPath = __DIR__ . '/../config/settings.php';
        if (file_exists($settingsPath)) {
            $settings = require $settingsPath;
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