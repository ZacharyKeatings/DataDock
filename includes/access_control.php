<?php
/**
 * v2.3.0 — Per-file access passwords, IP allowlists, signed download URLs.
 */

require_once __DIR__ . '/audit_log.php';

/**
 * Server-side secret for HMAC-signed temporary download URLs.
 */
function datadock_hmac_secret(PDO $pdo): string {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $st = $pdo->prepare("SELECT secret_value FROM app_secrets WHERE secret_key = 'signed_download_hmac' LIMIT 1");
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['secret_value'])) {
            $cached = (string) $row['secret_value'];
            return $cached;
        }
        $val = bin2hex(random_bytes(32));
        $ins = $pdo->prepare('INSERT INTO app_secrets (secret_key, secret_value) VALUES (?, ?)');
        $ins->execute(['signed_download_hmac', $val]);
        $cached = $val;
        return $cached;
    } catch (Throwable $e) {
        return hash('sha256', __DIR__ . 'datadock-fallback', true);
    }
}

function datadock_signed_download_build(PDO $pdo, int $fileId, int $expUnix): string {
    $msg = 'v1|' . $fileId . '|' . $expUnix;
    $secret = datadock_hmac_secret($pdo);
    return hash_hmac('sha256', $msg, $secret);
}

function datadock_signed_download_verify(PDO $pdo, int $fileId, int $expUnix, string $sigHex): bool {
    if ($expUnix < time()) {
        return false;
    }
    $expect = datadock_signed_download_build($pdo, $fileId, $expUnix);
    return hash_equals($expect, $sigHex);
}

/**
 * Optional approximate country when behind Cloudflare or similar.
 */
function datadock_client_country_code(): ?string {
    $cf = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
    if (is_string($cf) && strlen($cf) === 2 && strtoupper($cf) !== 'XX') {
        return strtoupper($cf);
    }
    return null;
}

function datadock_ipv4_in_cidr(string $ip, string $cidr): bool {
    if (strpos($cidr, '/') === false) {
        return $ip === $cidr;
    }
    $parts = explode('/', $cidr, 2);
    $subnet = trim($parts[0]);
    $mask = (int) ($parts[1] ?? 0);
    if ($mask < 0 || $mask > 32) {
        return false;
    }
    $ipLong = ip2long($ip);
    $subLong = ip2long($subnet);
    if ($ipLong === false || $subLong === false) {
        return false;
    }
    $maskLong = $mask === 0 ? 0 : (-1 << (32 - $mask)) & 0xFFFFFFFF;
    return (($ipLong & $maskLong) & 0xFFFFFFFF) === (($subLong & $maskLong) & 0xFFFFFFFF);
}

/**
 * @param string|null $json JSON array of IPv4/IPv6 addresses or IPv4 CIDR strings
 */
function datadock_ip_allowlist_allows(?string $json, string $clientIp): bool {
    if ($json === null || $json === '') {
        return true;
    }
    $list = json_decode($json, true);
    if (!is_array($list) || $list === []) {
        return true;
    }
    foreach ($list as $entry) {
        if (!is_string($entry)) {
            continue;
        }
        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }
        if (strpos($entry, ':') !== false) {
            if (strcasecmp($clientIp, $entry) === 0) {
                return true;
            }
            continue;
        }
        if (strpos($entry, '/') !== false) {
            if (datadock_ipv4_in_cidr($clientIp, $entry)) {
                return true;
            }
        } elseif ($clientIp === $entry) {
            return true;
        }
    }
    return false;
}

function datadock_session_has_file_access(int $fileId): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    $map = $_SESSION['datadock_file_access'] ?? [];
    if (!isset($map[$fileId])) {
        return false;
    }
    if ((int) $map[$fileId] < time()) {
        unset($_SESSION['datadock_file_access'][$fileId]);
        return false;
    }
    return true;
}

function datadock_session_grant_file_access(int $fileId, int $ttlSeconds = 3600): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    if (!isset($_SESSION['datadock_file_access'])) {
        $_SESSION['datadock_file_access'] = [];
    }
    $_SESSION['datadock_file_access'][$fileId] = time() + $ttlSeconds;
}

/**
 * Whether unauthenticated download (public / token / signed) may proceed.
 * Signed URLs and token links count as capability URLs and skip the password gate; IP allowlist always applies.
 */
function datadock_anonymous_access_allowed(array $file, bool $capabilityBypassPassword): bool {
    $fid = (int) $file['id'];
    $ip = datadock_client_ip();
    if (!datadock_ip_allowlist_allows($file['ip_allowlist'] ?? null, $ip)) {
        return false;
    }
    $hash = $file['access_password_hash'] ?? null;
    if (empty($hash)) {
        return true;
    }
    if ($capabilityBypassPassword) {
        return true;
    }
    return datadock_session_has_file_access($fid);
}

function datadock_parse_ip_allowlist_text(string $text): string {
    $lines = preg_split('/[\r\n,]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($lines)) {
        return json_encode([]);
    }
    $out = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return json_encode(array_values(array_unique($out)), JSON_UNESCAPED_SLASHES);
}

/**
 * HTML form for download password when not logged in as owner.
 */
function datadock_render_download_password_page(string $fileLabel, string $returnUrl, array $hiddenFields = []): void {
    header('Content-Type: text/html; charset=UTF-8');
    http_response_code(403);
    $title = 'Download password required';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . htmlspecialchars($title) . '</title>';
    echo '<link rel="stylesheet" href="' . htmlspecialchars(app_asset_url('assets/style.css'), ENT_QUOTES, 'UTF-8') . '">';
    echo '</head><body><div class="page-wrapper"><main class="container page-section">';
    echo '<h2 class="page-title">' . htmlspecialchars($title) . '</h2>';
    echo '<p>This file requires a password to download.</p><p><strong>File:</strong> ' . htmlspecialchars($fileLabel) . '</p>';
    echo '<form method="post" action="' . htmlspecialchars(app_script_url('download.php')) . '" class="settings-card" style="max-width:22rem">';
    echo '<input type="hidden" name="download_access" value="1">';
    foreach ($hiddenFields as $k => $v) {
        echo '<input type="hidden" name="' . htmlspecialchars((string) $k) . '" value="' . htmlspecialchars((string) $v) . '">';
    }
    echo '<div class="settings-card-body"><div class="form-group">';
    echo '<label for="access_password">Password</label>';
    echo '<input type="password" name="access_password" id="access_password" required autocomplete="off">';
    echo '</div><div class="form-actions"><button type="submit" class="btn btn-primary">Continue</button></div></div>';
    echo '</form>';
    if ($returnUrl !== '') {
        echo '<p><a href="' . htmlspecialchars($returnUrl) . '">Back</a></p>';
    }
    echo '</main></div></body></html>';
    exit;
}
