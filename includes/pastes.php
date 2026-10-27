<?php
/**
 * Unlisted plaintext pastes (share text with a link).
 */

function datadock_pastes_enabled(array $settings): bool {
    return !isset($settings['pastes_enabled']) || !empty($settings['pastes_enabled']);
}

/**
 * Byte cap for one paste. Follows the site file-size limit, and stays within MEDIUMTEXT.
 */
function datadock_paste_max_bytes(array $settings): int {
    $configured = (int) ($settings['max_file_size'] ?? 0);
    if ($configured < 1) {
        $configured = 5242880;
    }
    $mediumTextMax = 16777215;
    return min($configured, $mediumTextMax);
}

function datadock_paste_token_ok(string $token): bool {
    return (bool) preg_match('/^[a-f0-9]{32}$/', $token);
}

/**
 * @return array<string, string|null> key => DateTime modifier, or null for no expiry
 */
function datadock_paste_expiry_offsets(): array {
    return [
        '1_minute' => '+1 minute',
        '30_minutes' => '+30 minutes',
        '1_hour' => '+1 hour',
        '6_hours' => '+6 hours',
        '1_day' => '+1 day',
        '1_week' => '+1 week',
        '1_month' => '+1 month',
        '1_year' => '+1 year',
        'never' => null,
    ];
}

function datadock_paste_expiry_utc(?string $key, array $settings): ?string {
    $offsets = datadock_paste_expiry_offsets();
    if ($key === null || !array_key_exists($key, $offsets)) {
        $key = (string) ($settings['default_file_expiry'] ?? 'never');
        if (!array_key_exists($key, $offsets)) {
            $key = 'never';
        }
    }
    if ($offsets[$key] === null) {
        return null;
    }
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $now->modify($offsets[$key]);
    return $now->format('Y-m-d H:i:s');
}

function datadock_paste_strlen(string $value): int {
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($value, 'UTF-8');
    }
    $count = preg_match_all('/./u', $value, $unused);
    return $count === false ? strlen($value) : $count;
}

function datadock_paste_share_url(string $token): string {
    if (!function_exists('datadock_request_is_https')) {
        require_once __DIR__ . '/security_headers.php';
    }
    $scheme = datadock_request_is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = function_exists('app_script_url') ? app_script_url('paste.php') : '/paste.php';
    return $scheme . '://' . $host . $path . '?t=' . rawurlencode($token);
}

/**
 * Guest id cookie shared with uploads. Creates one only when $create is true.
 */
function datadock_paste_guest_id(bool $create): ?string {
    if (!empty($_SESSION['user_id'])) {
        return null;
    }
    $existing = $_COOKIE['guest_id'] ?? '';
    if (is_string($existing) && preg_match('/^[a-f0-9]{32}$/', $existing)) {
        return $existing;
    }
    if (!$create) {
        return null;
    }
    $id = bin2hex(random_bytes(16));
    setcookie('guest_id', $id, time() + 86400 * 30, '/');
    $_COOKIE['guest_id'] = $id;
    return $id;
}

function datadock_paste_is_creator(array $paste): bool {
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid && (int) ($paste['user_id'] ?? 0) === (int) $uid) {
        return true;
    }
    if (!empty($paste['user_id'])) {
        return false;
    }
    $guest = $_COOKIE['guest_id'] ?? '';
    $stored = (string) ($paste['guest_id'] ?? '');
    return $stored !== ''
        && is_string($guest)
        && strlen($stored) === strlen($guest)
        && hash_equals($stored, $guest);
}

function datadock_paste_can_delete(array $paste): bool {
    if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
        return true;
    }
    return datadock_paste_is_creator($paste);
}

function datadock_paste_by_token(PDO $pdo, string $token): ?array {
    if (!datadock_paste_token_ok($token)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM pastes WHERE token = ? AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Count a view. One-time pastes are deleted in the same transaction.
 *
 * @return array<string, mixed>|null Row plus _burned. Null when missing or expired.
 */
function datadock_paste_unlocked(int $pasteId): bool {
    $bag = $_SESSION['datadock_paste_access'] ?? [];
    return is_array($bag) && !empty($bag[$pasteId]);
}

function datadock_paste_grant(int $pasteId): void {
    if (!isset($_SESSION['datadock_paste_access']) || !is_array($_SESSION['datadock_paste_access'])) {
        $_SESSION['datadock_paste_access'] = [];
    }
    $_SESSION['datadock_paste_access'][$pasteId] = true;
}

function datadock_paste_open(PDO $pdo, string $token): ?array {
    if (!datadock_paste_token_ok($token)) {
        return null;
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM pastes WHERE token = ? FOR UPDATE');
        $stmt->execute([$token]);
        $paste = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$paste) {
            $pdo->rollBack();
            return null;
        }
        $expired = $pdo->prepare('SELECT (expires_at IS NOT NULL AND expires_at <= UTC_TIMESTAMP()) FROM pastes WHERE id = ?');
        $expired->execute([(int) $paste['id']]);
        if ((int) $expired->fetchColumn() === 1) {
            $pdo->rollBack();
            return null;
        }
        if (!empty($paste['burn_after_read'])) {
            $pdo->prepare('DELETE FROM pastes WHERE id = ?')->execute([(int) $paste['id']]);
            $paste['_burned'] = true;
        } else {
            $pdo->prepare('UPDATE pastes SET view_count = view_count + 1 WHERE id = ?')->execute([(int) $paste['id']]);
            $paste['view_count'] = (int) $paste['view_count'] + 1;
            $paste['_burned'] = false;
        }
        $pdo->commit();
        return $paste;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * @param array{user_id:?int,guest_id:?string,title:?string,content:string,expires_at:?string,burn_after_read:int,access_password_hash?:?string} $row
 * @return array{id:int,token:string}
 */
function datadock_paste_create(PDO $pdo, array $row): array {
    $last = null;
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $token = bin2hex(random_bytes(16));
        try {
            $stmt = $pdo->prepare('
                INSERT INTO pastes (user_id, guest_id, token, title, content, expires_at, burn_after_read, access_password_hash, view_count, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, UTC_TIMESTAMP())
            ');
            $stmt->execute([
                $row['user_id'],
                $row['guest_id'],
                $token,
                $row['title'],
                $row['content'],
                $row['expires_at'],
                $row['burn_after_read'],
                $row['access_password_hash'] ?? null,
            ]);
            return ['id' => (int) $pdo->lastInsertId(), 'token' => $token];
        } catch (PDOException $e) {
            $last = $e;
            $code = (int) ($e->errorInfo[1] ?? 0);
            if ($code !== 1062) {
                throw $e;
            }
        }
    }
    throw $last ?? new RuntimeException('Could not create paste');
}

/**
 * @return list<array<string, mixed>>
 */
function datadock_owned_pastes(PDO $pdo, int $limit = 20): array {
    $limit = max(1, min(50, $limit));
    $uid = $_SESSION['user_id'] ?? null;
    try {
        if ($uid) {
            $stmt = $pdo->prepare('
                SELECT id, token, title, expires_at, burn_after_read, view_count, created_at, OCTET_LENGTH(content) AS content_bytes
                FROM pastes
                WHERE user_id = ? AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
                ORDER BY created_at DESC
                LIMIT ' . $limit
            );
            $stmt->execute([(int) $uid]);
        } else {
            $guest = datadock_paste_guest_id(false);
            if ($guest === null) {
                return [];
            }
            $stmt = $pdo->prepare('
                SELECT id, token, title, expires_at, burn_after_read, view_count, created_at, OCTET_LENGTH(content) AS content_bytes
                FROM pastes
                WHERE guest_id = ? AND user_id IS NULL AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
                ORDER BY created_at DESC
                LIMIT ' . $limit
            );
            $stmt->execute([$guest]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function datadock_echo_owned_pastes(PDO $pdo, bool $readOnly, string $returnTo = 'paste.php', bool $allowCreate = true): int {
    if (!in_array($returnTo, ['dashboard.php', 'paste.php'], true)) {
        $returnTo = 'paste.php';
    }
    $rows = datadock_owned_pastes($pdo);
    if (!$rows) {
        return 0;
    }
    echo '<div class="paste-list">';
    echo '<div class="paste-list-heading">';
    echo '<h3 class="paste-list-title">Your pastes</h3>';
    if ($allowCreate && !$readOnly) {
        echo '<a href="paste.php" class="btn btn-quiet btn-small">New paste</a>';
    }
    echo '</div><ul>';
    foreach ($rows as $row) {
        $token = (string) ($row['token'] ?? '');
        if (!datadock_paste_token_ok($token)) {
            continue;
        }
        $url = datadock_paste_share_url($token);
        $title = trim((string) ($row['title'] ?? ''));
        $label = $title !== '' ? $title : ('Paste #' . (int) ($row['id'] ?? 0));
        $expires = $row['expires_at'] ?? null;
        echo '<li>';
        echo '<div class="paste-list-row">';
        echo '<span class="paste-list-label">' . sanitize_data($label) . '</span>';
        echo '<span class="paste-list-meta">';
        echo (int) ($row['view_count'] ?? 0) . ' views';
        echo ' · ' . sanitize_data(format_filesize((int) ($row['content_bytes'] ?? 0)));
        if (!empty($row['burn_after_read'])) {
            echo ' · one-time';
        }
        if ($expires) {
            echo ' · expires <span class="utc-datetime" data-utc="' . sanitize_data((string) $expires) . '"></span>';
        } else {
            echo ' · no expiry';
        }
        echo '</span>';
        if (!$readOnly) {
            echo '<form method="post" action="delete_paste.php" class="paste-delete-form" onsubmit="return confirm(\'Delete this paste? The link will stop working.\');">';
            echo datadock_csrf_field();
            echo '<input type="hidden" name="id" value="' . (int) ($row['id'] ?? 0) . '">';
            echo '<input type="hidden" name="return_to" value="' . sanitize_data($returnTo) . '">';
            echo '<button type="submit" class="btn btn-danger btn-small">Delete</button>';
            echo '</form>';
        }
        echo '</div>';
        echo '<input type="text" readonly class="paste-url" value="' . sanitize_data($url) . '" onclick="this.select();" aria-label="Share link for ' . sanitize_data($label) . '">';
        echo '</li>';
    }
    echo '</ul></div>';
    return count($rows);
}

function datadock_paste_download_name(?string $title): string {
    $base = trim((string) $title);
    $base = preg_replace('/[^A-Za-z0-9_-]+/', '-', $base) ?? '';
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'paste';
    }
    if (strlen($base) > 80) {
        $base = substr($base, 0, 80);
    }
    return $base . '.txt';
}

function datadock_paste_send_privacy_headers(): void {
    if (headers_sent()) {
        return;
    }
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');
}
