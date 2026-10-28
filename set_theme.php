<?php
/**
 * Sets the theme cookie. Signed-in users also store it as their account default.
 */
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/themes.php';

if (!empty($_SESSION['user_id'])) {
    $theme = isset($_GET['theme']) ? strtolower(trim((string) $_GET['theme'])) : '';
    if ($theme === 'site') {
        datadock_clear_theme_cookie();
        datadock_store_user_theme(null);
    } elseif (datadock_theme_is_known($theme)) {
        datadock_set_theme_cookie($theme);
        datadock_store_user_theme($theme);
    }
}

$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
$host = $_SERVER['HTTP_HOST'] ?? '';
$target = ($host !== '' && strpos($referer, $host) !== false) ? $referer : 'index.php';
header('Location: ' . $target);
exit;
