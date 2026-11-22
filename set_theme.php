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

$target = 'index.php';
$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
$parts = parse_url($referer);
if (is_array($parts)) {
    $refHost = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '');
    $query = (string) ($parts['query'] ?? '');
    $queryOk = $query === '' || preg_match('/^[A-Za-z0-9_%.&=+-]*$/', $query) === 1;
    if ($refHost !== '' && $refHost === datadock_request_hostname() && $path !== '' && !str_contains($path, '..') && !str_starts_with($path, '//') && $queryOk) {
        $target = $path . ($query !== '' ? '?' . $query : '');
    }
}
header('Location: ' . $target);
exit;
