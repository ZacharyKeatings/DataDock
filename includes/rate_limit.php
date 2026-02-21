<?php
/**
 * Rate limiting helpers for uploads and abuse prevention.
 * v1.8.0
 */

/**
 * Get client IP for rate limiting (respects X-Forwarded-For when from trusted proxy).
 *
 * @return string IPv4 or IPv6 address
 */
function get_client_ip(): string {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $val = trim($_SERVER[$key]);
            if (strpos($val, ',') !== false) {
                $val = trim(explode(',', $val)[0]);
            }
            if (filter_var($val, FILTER_VALIDATE_IP)) {
                return $val;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
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
