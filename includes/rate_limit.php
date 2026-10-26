<?php
/**
 * Rate limiting helpers for uploads and abuse prevention.
 * v1.8.0
 */

/**
 * True when two IP strings are the same address.
 */
function datadock_ips_equal(string $a, string $b): bool {
    $left = @inet_pton($a);
    $right = @inet_pton($b);
    if ($left !== false && $right !== false) {
        return $left === $right;
    }
    return strcasecmp($a, $b) === 0;
}

/**
 * IPv4 or IPv6 CIDR match. A value without "/" is an exact address.
 */
function datadock_ip_in_cidr(string $ip, string $cidr): bool {
    if (strpos($cidr, '/') === false) {
        return datadock_ips_equal($ip, $cidr);
    }
    $parts = explode('/', $cidr, 2);
    $subnet = $parts[0];
    if (!is_numeric($parts[1])) {
        return false;
    }
    $bits = (int) $parts[1];
    $ipBin = @inet_pton($ip);
    $subBin = @inet_pton($subnet);
    if ($ipBin === false || $subBin === false || strlen($ipBin) !== strlen($subBin)) {
        return false;
    }
    $max = strlen($ipBin) * 8;
    if ($bits < 0 || $bits > $max) {
        return false;
    }
    $bytes = intdiv($bits, 8);
    $rem = $bits % 8;
    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subBin, 0, $bytes)) {
        return false;
    }
    if ($rem === 0) {
        return true;
    }
    $mask = (0xFF << (8 - $rem)) & 0xFF;
    return (ord($ipBin[$bytes]) & $mask) === (ord($subBin[$bytes]) & $mask);
}

function datadock_trusted_proxy_entry_valid(string $entry): bool {
    $entry = trim($entry);
    if ($entry === '' || strlen($entry) > 64) {
        return false;
    }
    if (strpos($entry, '/') === false) {
        return filter_var($entry, FILTER_VALIDATE_IP) !== false;
    }
    $parts = explode('/', $entry, 2);
    if (filter_var($parts[0], FILTER_VALIDATE_IP) === false || !is_numeric($parts[1])) {
        return false;
    }
    $bin = @inet_pton($parts[0]);
    if ($bin === false) {
        return false;
    }
    $bits = (int) $parts[1];
    return $bits >= 0 && $bits <= (strlen($bin) * 8);
}

/**
 * Collapse a trusted-proxy list to valid addresses and CIDR blocks.
 */
function datadock_normalize_trusted_proxies(string $raw): string {
    $parts = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts)) {
        return '';
    }
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if (datadock_trusted_proxy_entry_valid($part)) {
            $out[] = $part;
        }
    }
    return implode(', ', $out);
}

/**
 * @return string[]
 */
function datadock_trusted_proxy_entries(): array {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    if (!function_exists('datadock_load_settings')) {
        require_once __DIR__ . '/settings_loader.php';
    }
    $settings = datadock_load_settings();
    $raw = $settings['trusted_proxies'] ?? '';
    if (!is_string($raw)) {
        $raw = '';
    }
    $normalized = datadock_normalize_trusted_proxies($raw);
    $cached = $normalized === '' ? [] : explode(', ', $normalized);
    return $cached;
}

/**
 * @param string[] $trusted
 */
function datadock_ip_is_trusted_proxy(string $ip, array $trusted): bool {
    foreach ($trusted as $entry) {
        if (datadock_ip_in_cidr($ip, $entry)) {
            return true;
        }
    }
    return false;
}

/**
 * @return string[]
 */
function datadock_forwarded_ip_chain(): array {
    $chain = [];
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if (is_string($xff) && $xff !== '') {
        foreach (explode(',', $xff) as $part) {
            $part = trim($part);
            if (filter_var($part, FILTER_VALIDATE_IP)) {
                $chain[] = $part;
            }
        }
    }
    if ($chain === []) {
        foreach (['HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP'] as $key) {
            $val = $_SERVER[$key] ?? '';
            if (!is_string($val)) {
                continue;
            }
            $val = trim($val);
            if (filter_var($val, FILTER_VALIDATE_IP)) {
                $chain[] = $val;
                break;
            }
        }
    }
    return $chain;
}

/**
 * Client address for limits, allowlists, and logs.
 * Forwarded headers are used only when the TCP peer is a configured trusted proxy.
 *
 * @return string IPv4 or IPv6 address
 */
function get_client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($remote) || filter_var($remote, FILTER_VALIDATE_IP) === false) {
        return '0.0.0.0';
    }
    $trusted = datadock_trusted_proxy_entries();
    if ($trusted === [] || !datadock_ip_is_trusted_proxy($remote, $trusted)) {
        return $remote;
    }
    $chain = datadock_forwarded_ip_chain();
    for ($i = count($chain) - 1; $i >= 0; $i--) {
        if (!datadock_ip_is_trusted_proxy($chain[$i], $trusted)) {
            return $chain[$i];
        }
    }
    if ($chain !== []) {
        return $chain[0];
    }
    return $remote;
}

/**
 * Check if the current request is within upload rate limits (per IP and per user).
 * Call before processing uploads. Returns true if allowed, false if throttled.
 *
 * @param PDO $pdo
 * @param int|null $userId Logged-in user ID or null for guest
 * @param string $ip Client IP (from get_client_ip())
 * @param array $settings Application settings (rate_limit_uploads, etc.)
 * @return bool True if allowed
 */
function check_upload_rate_limit(PDO $pdo, ?int $userId, string $ip, array $settings): bool {
    $cfg = $settings['rate_limit_uploads'] ?? null;
    if (empty($cfg) || empty($cfg['enabled'])) {
        return true;
    }
    $windowMinutes = (int) ($cfg['window_minutes'] ?? 1);
    $maxPerIp = (int) ($cfg['max_per_ip'] ?? 0);
    $maxPerUser = (int) ($cfg['max_per_user'] ?? 0);
    if ($maxPerIp <= 0 && $maxPerUser <= 0) {
        return true;
    }
    $windowStart = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));

    if ($maxPerIp > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM upload_rate_log WHERE ip_address = ? AND created_at > ?");
        $stmt->execute([$ip, $windowStart]);
        if ($stmt->fetchColumn() >= $maxPerIp) {
            return false;
        }
    }
    if ($maxPerUser > 0 && $userId !== null) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM upload_rate_log WHERE user_id = ? AND created_at > ?");
        $stmt->execute([$userId, $windowStart]);
        if ($stmt->fetchColumn() >= $maxPerUser) {
            return false;
        }
    }
    return true;
}

/**
 * Record one upload event for rate limiting. Call after a successful upload (per file or per batch).
 *
 * @param PDO $pdo
 * @param string $ip Client IP
 * @param int|null $userId User ID or null for guest
 * @param int $count Number of upload events to log (e.g. number of files in batch)
 */
function record_upload_rate_limit(PDO $pdo, string $ip, ?int $userId, int $count = 1): void {
    $stmt = $pdo->prepare("INSERT INTO upload_rate_log (ip_address, user_id, created_at) VALUES (?, ?, UTC_TIMESTAMP())");
    for ($i = 0; $i < $count; $i++) {
        $stmt->execute([$ip, $userId]);
    }
}
