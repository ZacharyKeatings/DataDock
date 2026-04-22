<?php
/**
 * Optional HTTP response hardening (v2.5+). See README Security and examples/*.conf.
 */

/**
 * Whether the current request is served over HTTPS (including common reverse-proxy headers).
 */
function datadock_request_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    $xfp = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($xfp === 'https') {
        return true;
    }
    return ((int) ($_SERVER['SERVER_PORT'] ?? 0)) === 443;
}

/**
 * Send security-related headers based on config key security_headers_mode:
 * off | recommended | strict
 *
 * "strict" includes a CSP that allows this app’s inline scripts and the Activity chart CDN;
 * tighten further only after auditing your deployment.
 */
function datadock_send_response_security_headers(array $settings): void {
    if (headers_sent()) {
        return;
    }
    $mode = strtolower(trim((string) ($settings['security_headers_mode'] ?? 'off')));
    if ($mode === '' || $mode === 'off' || $mode === '0' || $mode === 'false') {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');

    if ($mode === 'recommended') {
        header('X-Frame-Options: SAMEORIGIN');
        return;
    }

    if ($mode !== 'strict') {
        return;
    }

    header('X-Frame-Options: DENY');
    header('Cross-Origin-Opener-Policy: same-origin');
    $csp = implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
        "img-src 'self' data: blob: https:",
        "font-src 'self'",
        "style-src 'self' 'unsafe-inline'",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
        "connect-src 'self' https://cdn.jsdelivr.net",
    ]);
    header('Content-Security-Policy: ' . $csp);
}
