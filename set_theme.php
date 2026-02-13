<?php
/**
 * Sets theme preference via cookie and redirects back.
 */
$theme = isset($_GET['theme']) ? strtolower(trim($_GET['theme'])) : '';
if (in_array($theme, ['light', 'dark'], true)) {
    setcookie('datadock_theme', $theme, time() + 86400 * 365, '/');
}
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . (strpos($referer, $_SERVER['HTTP_HOST'] ?? '') !== false ? $referer : 'index.php'));
exit;
