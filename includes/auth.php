<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Initializes the session. This function can also include additional auth checks.
 */
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Returns the current page's basename.
 *
 * @return string
 */
function get_current_page() {
    return basename($_SERVER['PHP_SELF']);
}

/**
 * Returns the site name from settings.
 *
 * @return string
 */
function get_site_name() {
    global $settings;
    return $settings['site_name'] ?? 'File Upload Site';
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