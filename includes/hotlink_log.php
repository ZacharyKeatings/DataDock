<?php
/**
 * Log requests that reference file/thumbnail/avatar URLs with an off-site Referer.
 */

/**
 * Normalize host for comparison (lowercase, strip bracketed IPv6 and trailing port).
 */
function hotlink_normalize_host(string $host): string {
    $host = strtolower(trim($host));
    if ($host !== '' && $host[0] === '[') {
        $end = strpos($host, ']');
        if ($end !== false) {
            return substr($host, 1, $end - 1);
        }
    }
    if (strpos($host, ':') !== false) {
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    }
    return $host;
}

/**
 * Trusted referer hosts: current HTTP_HOST plus optional extra from settings.
 *
 * @param array<string, mixed> $settings
 * @return list<string>
 */
function hotlink_trusted_hosts_for_request(array $settings): array {
    $hosts = [];
    $h = $_SERVER['HTTP_HOST'] ?? '';
    if ($h !== '') {
        $hosts[] = hotlink_normalize_host($h);
    }
    $extra = trim((string) ($settings['hotlink_trusted_hosts'] ?? ''));
    if ($extra !== '') {
        foreach (preg_split('/[\s,]+/', $extra, -1, PREG_SPLIT_NO_EMPTY) as $p) {
            $hosts[] = hotlink_normalize_host($p);
        }
    }
    return array_values(array_unique(array_filter($hosts)));
}

/**
 * True if referer is empty or its host matches a trusted host.
 *
 * @param list<string> $trustedHosts
 */
function hotlink_referer_is_trusted(?string $referer, array $trustedHosts): bool {
    if ($referer === null || trim($referer) === '') {
        return true;
    }
    $parts = @parse_url($referer);
    if (empty($parts['host']) || !is_string($parts['host'])) {
        return true;
    }
    $ref = hotlink_normalize_host($parts['host']);
    foreach ($trustedHosts as $ok) {
        if ($ref === $ok) {
            return true;
        }
    }
    return false;
}

/**
 * Insert a row when logging is on and Referer indicates another site.
 *
 * @param array<string, mixed> $settings
 */
function datadock_log_hotlink_if_external(PDO $pdo, array $settings, string $resource, ?int $fileId = null, ?int $targetUserId = null): void {
    $loggingOn = !array_key_exists('hotlink_logging_enabled', $settings) || !empty($settings['hotlink_logging_enabled']);
    if (!$loggingOn) {
        return;
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $trusted = hotlink_trusted_hosts_for_request($settings);
    if (hotlink_referer_is_trusted($referer, $trusted)) {
        return;
    }
    if (!function_exists('get_client_ip')) {
        require_once __DIR__ . '/rate_limit.php';
    }
    $resource = substr(preg_replace('/[^a-z0-9_\-]/i', '', $resource) ?? '', 0, 32);
    if ($resource === '') {
        $resource = 'unknown';
    }
    $refHost = '';
    $p = parse_url($referer);
    if (!empty($p['host']) && is_string($p['host'])) {
        $refHost = substr(hotlink_normalize_host($p['host']), 0, 255);
    }
    $refTrunc = substr($referer, 0, 2048);
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
    $ip = get_client_ip();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO hotlink_log (resource, file_id, target_user_id, referer, referer_host, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$resource, $fileId, $targetUserId, $refTrunc, $refHost, substr($ip, 0, 45), $ua]);
    } catch (PDOException $e) {
        // Table missing or DB error — do not break downloads
    }
}
