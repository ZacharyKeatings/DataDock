<?php
require_once __DIR__ . '/settings_loader.php';

/**
 * Stop the request if the instance is in read-only (archive) mode.
 *
 * @param string $loggedInRedirect e.g. dashboard.php
 * @param string $anonRedirect     e.g. index.php or login.php
 */
function datadock_block_if_read_only(array $settings, string $loggedInRedirect = 'dashboard.php', string $anonRedirect = 'index.php'): void {
    if (!datadock_read_only_enabled($settings)) {
        return;
    }
    $_SESSION['flash_error'][] = '❌ This site is in read-only (archive) mode. Only downloads are allowed; uploads and file changes are disabled.';
    if (!empty($_SESSION['user_id'])) {
        header('Location: ' . $loggedInRedirect);
    } else {
        header('Location: ' . $anonRedirect);
    }
    exit;
}
