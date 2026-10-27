<?php
/**
 * TOTP, session list, OIDC, LDAP, and forward-auth sign-in.
 * Local passwords stay available. Directory and proxy login are optional.
 */

function datadock_identity_config(array $settings): array {
    $id = is_array($settings['identity'] ?? null) ? $settings['identity'] : [];
    $policy = strtolower(trim((string) ($id['totp_policy'] ?? 'off')));
    if (!in_array($policy, ['off', 'optional', 'required_admin'], true)) {
        $policy = 'off';
    }
    $oidc = is_array($id['oidc'] ?? null) ? $id['oidc'] : [];
    $ldap = is_array($id['ldap'] ?? null) ? $id['ldap'] : [];
    $fwd = is_array($id['forward_auth'] ?? null) ? $id['forward_auth'] : [];
    $enc = strtolower(trim((string) ($ldap['encryption'] ?? 'none')));
    if (!in_array($enc, ['none', 'starttls', 'ldaps'], true)) {
        $enc = 'none';
    }
    return [
        'totp_policy' => $policy,
        'oidc' => [
            'enabled' => !empty($oidc['enabled']),
            'issuer' => rtrim(trim((string) ($oidc['issuer'] ?? '')), '/'),
            'client_id' => trim((string) ($oidc['client_id'] ?? '')),
            'client_secret' => (string) ($oidc['client_secret'] ?? ''),
            'role_claim' => trim((string) ($oidc['role_claim'] ?? 'groups')) ?: 'groups',
            'admin_groups' => trim((string) ($oidc['admin_groups'] ?? '')),
        ],
        'ldap' => [
            'enabled' => !empty($ldap['enabled']),
            'host' => trim((string) ($ldap['host'] ?? '')),
            'port' => (int) ($ldap['port'] ?? 389),
            'encryption' => $enc,
            'user_dn_template' => trim((string) ($ldap['user_dn_template'] ?? '')),
            'base_dn' => trim((string) ($ldap['base_dn'] ?? '')),
            'bind_dn' => trim((string) ($ldap['bind_dn'] ?? '')),
            'bind_password' => (string) ($ldap['bind_password'] ?? ''),
            'user_filter' => trim((string) ($ldap['user_filter'] ?? '')) ?: '(uid=%s)',
            'admin_group' => trim((string) ($ldap['admin_group'] ?? '')),
        ],
        'forward_auth' => [
            'enabled' => !empty($fwd['enabled']),
            'trusted_proxies' => trim((string) ($fwd['trusted_proxies'] ?? '')),
            'user_header' => trim((string) ($fwd['user_header'] ?? '')) ?: 'Remote-User',
            'group_header' => trim((string) ($fwd['group_header'] ?? '')) ?: 'Remote-Groups',
            'admin_groups' => trim((string) ($fwd['admin_groups'] ?? '')),
        ],
    ];
}

function datadock_base32_encode(string $bin): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    $len = strlen($bin);
    for ($i = 0; $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        }
        $out .= $alphabet[bindec($chunk)];
    }
    return $out;
}

function datadock_base32_decode(string $text): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $text = strtoupper(preg_replace('/[^A-Z2-7]/', '', $text) ?? '');
    $bits = '';
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $pos = strpos($alphabet, $text[$i]);
        if ($pos === false) {
            continue;
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) < 8) {
            break;
        }
        $out .= chr(bindec($chunk));
    }
    return $out;
}

function datadock_totp_code(string $secretBin, ?int $time = null): string {
    $counter = intdiv($time ?? time(), 30);
    $bin = pack('N', 0) . pack('N', $counter);
    $hash = hash_hmac('sha1', $bin, $secretBin, true);
    $offset = ord($hash[19]) & 0x0f;
    $value = (
        ((ord($hash[$offset]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    ) % 1000000;
    return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
}

function datadock_totp_verify(string $secretBase32, string $code): bool {
    $code = preg_replace('/\s+/', '', $code) ?? '';
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    $secret = datadock_base32_decode($secretBase32);
    if ($secret === '') {
        return false;
    }
    $now = time();
    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(datadock_totp_code($secret, $now + ($i * 30)), $code)) {
            return true;
        }
    }
    return false;
}

function datadock_user_needs_totp(PDO $pdo, int $userId): bool {
    try {
        $stmt = $pdo->prepare('SELECT totp_enabled, totp_secret FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
    return $row && !empty($row['totp_enabled']) && !empty($row['totp_secret']);
}

/**
 * Finish a password (or directory) login. Starts a TOTP step when the account has it on.
 */
function datadock_login_after_password(PDO $pdo, array $user, array $settings, bool $remember): void {
    $claim = trim((string) ($_POST['claim_code'] ?? ''));
    if ($claim !== '') {
        $_SESSION['datadock_claim_code'] = $claim;
    }
    $userId = (int) $user['id'];
    if (datadock_user_needs_totp($pdo, $userId)) {
        $_SESSION['totp_pending'] = [
            'user_id' => $userId,
            'remember' => $remember,
            'expires' => time() + 300,
        ];
        header('Location: login.php?totp=1');
        exit;
    }
    datadock_establish_session($pdo, $user, $settings, $remember);
}

function datadock_establish_session(PDO $pdo, array $user, array $settings, bool $remember, bool $redirect = true): void {
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = (string) ($user['username'] ?? '');
    $_SESSION['role'] = (($user['role'] ?? '') === 'admin') ? 'admin' : 'user';
    unset($_SESSION['totp_pending']);
    datadock_finalize_login_session($settings, $remember);
    datadock_session_record($pdo, (int) $user['id']);
    $claim = trim((string) ($_POST['claim_code'] ?? $_SESSION['datadock_claim_code'] ?? ''));
    unset($_SESSION['datadock_claim_code']);
    if ($claim !== '') {
        require_once __DIR__ . '/guest_claim.php';
        $result = datadock_claim_guest_uploads($pdo, $settings, (int) $user['id'], $claim);
        if ($result['message'] !== '') {
            if ($result['ok']) {
                $_SESSION['flash_success'][] = $result['message'];
            } else {
                $_SESSION['flash_error'][] = $result['message'];
            }
        }
    }
    if ($redirect) {
        $_SESSION['flash_success'][] = 'Login successful. Welcome, ' . (string) ($user['username'] ?? '') . '!';
        header('Location: dashboard.php');
        exit;
    }
}

function datadock_session_hash(): string {
    return hash('sha256', session_id());
}

function datadock_session_record(PDO $pdo, int $userId): void {
    $hash = datadock_session_hash();
    $ip = function_exists('get_client_ip') ? substr(get_client_ip(), 0, 45) : null;
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    try {
        $pdo->prepare('
            INSERT INTO user_sessions (user_id, session_hash, ip_address, user_agent, created_at, last_seen)
            VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), ip_address = VALUES(ip_address), user_agent = VALUES(user_agent), last_seen = UTC_TIMESTAMP(), revoked_at = NULL
        ')->execute([$userId, $hash, $ip, $ua]);
    } catch (Throwable $e) {
    }
}

function datadock_session_touch(PDO $pdo, int $userId): void {
    $now = time();
    $last = (int) ($_SESSION['datadock_session_touch'] ?? 0);
    if ($last > 0 && ($now - $last) < 60) {
        return;
    }
    $_SESSION['datadock_session_touch'] = $now;
    $hash = datadock_session_hash();
    try {
        $stmt = $pdo->prepare('SELECT revoked_at FROM user_sessions WHERE session_hash = ?');
        $stmt->execute([$hash]);
        $revoked = $stmt->fetchColumn();
        if ($revoked === false) {
            datadock_session_record($pdo, $userId);
            return;
        }
        if ($revoked !== null && $revoked !== '') {
            unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['datadock_remember_device']);
            $_SESSION['flash_error'][] = 'This session was revoked.';
            return;
        }
        $pdo->prepare('UPDATE user_sessions SET last_seen = UTC_TIMESTAMP() WHERE session_hash = ?')->execute([$hash]);
    } catch (Throwable $e) {
    }
}

function datadock_admin_totp_gate(PDO $pdo, array $settings): void {
    $policy = datadock_identity_config($settings)['totp_policy'];
    if ($policy !== 'required_admin') {
        return;
    }
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($script, ['profile.php', 'logout.php', 'login.php'], true)) {
        return;
    }
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId < 1 || (($_SESSION['role'] ?? '') !== 'admin')) {
        return;
    }
    if (datadock_user_needs_totp($pdo, $userId)) {
        return;
    }
    $_SESSION['flash_error'][] = 'Turn on an authenticator code before using the admin panel.';
    header('Location: profile.php');
    exit;
}

function datadock_asn1_len(int $len): string {
    if ($len < 128) {
        return chr($len);
    }
    $bin = ltrim(pack('N', $len), "\x00");
    return chr(0x80 | strlen($bin)) . $bin;
}

function datadock_rsa_pem(string $nBin, string $eBin): string {
    if ($nBin !== '' && (ord($nBin[0]) & 0x80) !== 0) {
        $nBin = "\x00" . $nBin;
    }
    if ($eBin !== '' && (ord($eBin[0]) & 0x80) !== 0) {
        $eBin = "\x00" . $eBin;
    }
    $nEnc = "\x02" . datadock_asn1_len(strlen($nBin)) . $nBin;
    $eEnc = "\x02" . datadock_asn1_len(strlen($eBin)) . $eBin;
    $seq = "\x30" . datadock_asn1_len(strlen($nEnc . $eEnc)) . $nEnc . $eEnc;
    $bit = "\x03" . datadock_asn1_len(strlen($seq) + 1) . "\x00" . $seq;
    $alg = hex2bin('300d06092a864886f70d0101010500');
    $body = $alg . $bit;
    $spki = "\x30" . datadock_asn1_len(strlen($body)) . $body;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function datadock_b64url_decode(string $data): string {
    $data = strtr($data, '-_', '+/');
    $pad = strlen($data) % 4;
    if ($pad > 0) {
        $data .= str_repeat('=', 4 - $pad);
    }
    $out = base64_decode($data, true);
    return is_string($out) ? $out : '';
}

function datadock_http_json(string $method, string $url, array $headers = [], ?string $body = null): ?array {
    $headerLines = $headers;
    $raw = false;
    $code = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $raw = is_string($resp) ? $resp : false;
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $body ?? '',
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
    }
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function datadock_groups_grant_admin(string $configured, $claim): bool {
    $want = array_filter(array_map('trim', preg_split('/[,\n]+/', $configured) ?: []));
    if ($want === []) {
        return false;
    }
    $have = [];
    if (is_string($claim)) {
        $have = array_map('trim', preg_split('/[,\s]+/', $claim) ?: []);
    } elseif (is_array($claim)) {
        foreach ($claim as $item) {
            if (is_string($item)) {
                $have[] = trim($item);
            }
        }
    }
    foreach ($want as $group) {
        foreach ($have as $got) {
            if ($got !== '' && strcasecmp($got, $group) === 0) {
                return true;
            }
        }
    }
    return false;
}

function datadock_unique_username(PDO $pdo, string $base): string {
    $base = preg_replace('/[^A-Za-z0-9._-]/', '', $base) ?? '';
    if ($base === '') {
        $base = 'user';
    }
    $base = substr($base, 0, 40);
    $name = $base;
    for ($i = 0; $i < 50; $i++) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$name]);
        if (!$stmt->fetch()) {
            return $name;
        }
        $name = substr($base, 0, 36) . $i;
    }
    return $base . bin2hex(random_bytes(3));
}

/**
 * @param array{sub:string,email?:string,name?:string,preferred_username?:string,role:string} $profile
 */
function datadock_upsert_external_user(PDO $pdo, array $profile, string $source): array {
    $sub = substr((string) $profile['sub'], 0, 255);
    $email = strtolower(trim((string) ($profile['email'] ?? '')));
    $role = ($profile['role'] ?? '') === 'admin' ? 'admin' : 'user';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE external_subject = ?');
    $stmt->execute([$sub]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $pdo->prepare('UPDATE users SET external_subject = ?, auth_source = ? WHERE id = ? AND (external_subject IS NULL OR external_subject = ?)')->execute([
                $sub, $source, (int) $user['id'], $sub,
            ]);
        }
    }
    if ($user) {
        if (!empty($user['suspended'])) {
            throw new RuntimeException('This account is suspended.');
        }
        if ($role === 'user' && ($user['role'] ?? '') === 'admin') {
            $others = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND suspended = 0 AND id != " . (int) $user['id'])->fetchColumn();
            if ($others < 1) {
                $role = 'admin';
            }
        }
        $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, (int) $user['id']]);
        $user['role'] = $role;
        return $user;
    }
    $prefer = (string) ($profile['preferred_username'] ?? '');
    if ($prefer === '' && $email !== '') {
        $prefer = strstr($email, '@', true) ?: $email;
    }
    $username = datadock_unique_username($pdo, $prefer);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = $username . '@users.datadock.local';
    }
    $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO users (username, email, password_hash, role, auth_source, external_subject) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        $username, $email, $hash, $role, $source, $sub,
    ]);
    $id = (int) $pdo->lastInsertId();
    return ['id' => $id, 'username' => $username, 'role' => $role, 'email' => $email];
}

function datadock_oidc_start(array $settings): void {
    $oidc = datadock_identity_config($settings)['oidc'];
    if (!$oidc['enabled'] || $oidc['issuer'] === '' || $oidc['client_id'] === '') {
        $_SESSION['flash_error'][] = 'OpenID Connect is not configured.';
        header('Location: login.php');
        exit;
    }
    $doc = datadock_http_json('GET', $oidc['issuer'] . '/.well-known/openid-configuration', ['Accept: application/json']);
    $auth = is_array($doc) ? (string) ($doc['authorization_endpoint'] ?? '') : '';
    if ($auth === '') {
        $_SESSION['flash_error'][] = 'The identity provider discovery document could not be read.';
        header('Location: login.php');
        exit;
    }
    $state = bin2hex(random_bytes(16));
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['oidc_state'] = $state;
    $_SESSION['oidc_nonce'] = $nonce;
    $redirect = datadock_public_url('oidc.php');
    $q = http_build_query([
        'response_type' => 'code',
        'client_id' => $oidc['client_id'],
        'redirect_uri' => $redirect,
        'scope' => 'openid email profile',
        'state' => $state,
        'nonce' => $nonce,
    ]);
    header('Location: ' . $auth . (strpos($auth, '?') === false ? '?' : '&') . $q);
    exit;
}

function datadock_public_url(string $script): string {
    $https = function_exists('datadock_request_is_https') ? datadock_request_is_https() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . ($dir === '' || $dir === '.' ? '' : $dir) . '/' . ltrim($script, '/');
}

function datadock_jwt_rs256_payload(string $jwt, array $jwks, string $issuer, string $audience, string $nonce): ?array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }
    $header = json_decode(datadock_b64url_decode($parts[0]), true);
    $payload = json_decode(datadock_b64url_decode($parts[1]), true);
    if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'RS256') {
        return null;
    }
    $kid = (string) ($header['kid'] ?? '');
    $pem = '';
    $keys = is_array($jwks['keys'] ?? null) ? $jwks['keys'] : [];
    foreach ($keys as $key) {
        if (!is_array($key) || ($key['kty'] ?? '') !== 'RSA') {
            continue;
        }
        if ($kid !== '' && (string) ($key['kid'] ?? '') !== $kid) {
            continue;
        }
        $n = datadock_b64url_decode((string) ($key['n'] ?? ''));
        $e = datadock_b64url_decode((string) ($key['e'] ?? ''));
        if ($n !== '' && $e !== '') {
            $pem = datadock_rsa_pem($n, $e);
            break;
        }
    }
    if ($pem === '') {
        return null;
    }
    $sig = datadock_b64url_decode($parts[2]);
    $ok = openssl_verify($parts[0] . '.' . $parts[1], $sig, $pem, OPENSSL_ALGO_SHA256);
    if ($ok !== 1) {
        return null;
    }
    if (($payload['iss'] ?? '') !== $issuer) {
        return null;
    }
    $aud = $payload['aud'] ?? '';
    $audOk = $aud === $audience || (is_array($aud) && in_array($audience, $aud, true));
    if (!$audOk) {
        return null;
    }
    if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
        return null;
    }
    if ($nonce !== '' && (($payload['nonce'] ?? '') !== $nonce)) {
        return null;
    }
    return $payload;
}

function datadock_oidc_finish(PDO $pdo, array $settings, string $code, string $state): void {
    $oidc = datadock_identity_config($settings)['oidc'];
    $expect = (string) ($_SESSION['oidc_state'] ?? '');
    $nonce = (string) ($_SESSION['oidc_nonce'] ?? '');
    unset($_SESSION['oidc_state'], $_SESSION['oidc_nonce']);
    if ($expect === '' || !hash_equals($expect, $state)) {
        $_SESSION['flash_error'][] = 'The sign-in response could not be confirmed. Try again.';
        header('Location: login.php');
        exit;
    }
    $doc = datadock_http_json('GET', $oidc['issuer'] . '/.well-known/openid-configuration', ['Accept: application/json']);
    if (!is_array($doc)) {
        $_SESSION['flash_error'][] = 'The identity provider discovery document could not be read.';
        header('Location: login.php');
        exit;
    }
    $tokenUrl = (string) ($doc['token_endpoint'] ?? '');
    $jwksUrl = (string) ($doc['jwks_uri'] ?? '');
    $body = http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => datadock_public_url('oidc.php'),
        'client_id' => $oidc['client_id'],
        'client_secret' => $oidc['client_secret'],
    ]);
    $token = datadock_http_json('POST', $tokenUrl, ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'], $body);
    $idToken = is_array($token) ? (string) ($token['id_token'] ?? '') : '';
    $jwks = datadock_http_json('GET', $jwksUrl, ['Accept: application/json']);
    if ($idToken === '' || !is_array($jwks)) {
        $_SESSION['flash_error'][] = 'The identity provider did not return a usable sign-in token.';
        header('Location: login.php');
        exit;
    }
    $payload = datadock_jwt_rs256_payload($idToken, $jwks, $oidc['issuer'], $oidc['client_id'], $nonce);
    if ($payload === null || empty($payload['sub'])) {
        $_SESSION['flash_error'][] = 'The identity token could not be verified.';
        header('Location: login.php');
        exit;
    }
    $claimName = $oidc['role_claim'];
    $role = datadock_groups_grant_admin($oidc['admin_groups'], $payload[$claimName] ?? null) ? 'admin' : 'user';
    try {
        $user = datadock_upsert_external_user($pdo, [
            'sub' => (string) $payload['sub'],
            'email' => (string) ($payload['email'] ?? ''),
            'preferred_username' => (string) ($payload['preferred_username'] ?? ''),
            'role' => $role,
        ], 'oidc');
    } catch (RuntimeException $e) {
        $_SESSION['flash_error'][] = $e->getMessage();
        header('Location: login.php');
        exit;
    }
    datadock_login_after_password($pdo, $user, $settings, false);
}

/**
 * @return array{ok:bool,role:string,dn:string}|null
 */
function datadock_ldap_authenticate(array $ldap, string $username, string $password): ?array {
    if (!$ldap['enabled'] || $ldap['host'] === '' || $password === '') {
        return null;
    }
    if (!function_exists('ldap_connect')) {
        return null;
    }
    $port = $ldap['port'] > 0 ? $ldap['port'] : ($ldap['encryption'] === 'ldaps' ? 636 : 389);
    $scheme = $ldap['encryption'] === 'ldaps' ? 'ldaps' : 'ldap';
    $conn = @ldap_connect($scheme . '://' . $ldap['host'] . ':' . $port);
    if ($conn === false) {
        return null;
    }
    ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
    if ($ldap['encryption'] === 'starttls' && !@ldap_start_tls($conn)) {
        return null;
    }
    $filterUser = function_exists('ldap_escape') ? ldap_escape($username, '', LDAP_ESCAPE_FILTER) : str_replace(['(', ')', '*', '\\', "\0"], '', $username);
    $dnUser = function_exists('ldap_escape') ? ldap_escape($username, '', LDAP_ESCAPE_DN) : $filterUser;
    $userDn = '';
    if ($ldap['user_dn_template'] !== '' && strpos($ldap['user_dn_template'], '%s') !== false) {
        $userDn = str_replace('%s', $dnUser, $ldap['user_dn_template']);
    } elseif ($ldap['base_dn'] !== '' && $ldap['bind_dn'] !== '') {
        if (!@ldap_bind($conn, $ldap['bind_dn'], $ldap['bind_password'])) {
            return null;
        }
        $filter = str_replace('%s', $filterUser, $ldap['user_filter']);
        $search = @ldap_search($conn, $ldap['base_dn'], $filter, ['dn', 'memberOf']);
        $entries = $search ? ldap_get_entries($conn, $search) : ['count' => 0];
        if (($entries['count'] ?? 0) < 1) {
            return null;
        }
        $userDn = (string) ($entries[0]['dn'] ?? '');
    }
    if ($userDn === '' || !@ldap_bind($conn, $userDn, $password)) {
        return null;
    }
    $role = 'user';
    if ($ldap['admin_group'] !== '') {
        $search = @ldap_search($conn, $userDn, '(objectClass=*)', ['memberOf']);
        $entries = $search ? ldap_get_entries($conn, $search) : ['count' => 0];
        $groups = $entries[0]['memberof'] ?? [];
        $list = [];
        if (is_array($groups)) {
            foreach ($groups as $k => $value) {
                if ($k === 'count' || !is_string($value)) {
                    continue;
                }
                $list[] = $value;
                if (preg_match('/^CN=([^,]+)/i', $value, $m)) {
                    $list[] = $m[1];
                }
            }
        }
        if (datadock_groups_grant_admin($ldap['admin_group'], $list)) {
            $role = 'admin';
        }
    }
    return ['ok' => true, 'role' => $role, 'dn' => $userDn];
}

function datadock_request_header(string $name): string {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = $_SERVER[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function datadock_forward_auth_maybe(PDO $pdo, array $settings): void {
    if (!empty($_SESSION['user_id']) || !empty($_SESSION['totp_pending'])) {
        return;
    }
    if (!empty($_COOKIE['datadock_local_login'])) {
        return;
    }
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($script, ['logout.php', 'oidc.php', 'install.php'], true)) {
        return;
    }
    $fwd = datadock_identity_config($settings)['forward_auth'];
    if (!$fwd['enabled'] || $fwd['trusted_proxies'] === '') {
        return;
    }
    if (!function_exists('datadock_parse_ip_allowlist_text')) {
        require_once __DIR__ . '/access_control.php';
    }
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $json = datadock_parse_ip_allowlist_text($fwd['trusted_proxies']);
    if (!datadock_ip_allowlist_allows($json, $remote)) {
        return;
    }
    $name = datadock_request_header($fwd['user_header']);
    if ($name === '' || strlen($name) > 200) {
        return;
    }
    $groups = datadock_request_header($fwd['group_header']);
    $role = datadock_groups_grant_admin($fwd['admin_groups'], $groups) ? 'admin' : 'user';
    try {
        $user = datadock_upsert_external_user($pdo, [
            'sub' => 'fwd:' . strtolower($name),
            'preferred_username' => $name,
            'email' => filter_var($name, FILTER_VALIDATE_EMAIL) ? $name : '',
            'role' => $role,
        ], 'forward');
    } catch (Throwable $e) {
        return;
    }
    if (datadock_user_needs_totp($pdo, (int) $user['id'])) {
        $_SESSION['totp_pending'] = [
            'user_id' => (int) $user['id'],
            'remember' => false,
            'expires' => time() + 300,
        ];
        header('Location: login.php?totp=1');
        exit;
    }
    datadock_establish_session($pdo, $user, $settings, false, false);
}
