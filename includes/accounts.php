<?php
/**
 * Account lifecycle helpers: password policy, suspension, self-service close.
 */

/**
 * @param array<string, mixed> $settings
 * @return array{min_length:int,complexity:bool}
 */
function datadock_password_policy(array $settings): array {
    $rules = is_array($settings['password_rules'] ?? null) ? $settings['password_rules'] : [];
    $min = (int) ($rules['min_length'] ?? 6);
    if ($min < 6) {
        $min = 6;
    }
    if ($min > 128) {
        $min = 128;
    }
    return [
        'min_length' => $min,
        'complexity' => !empty($rules['complexity']),
    ];
}

/**
 * @param array<string, mixed> $settings
 */
function datadock_password_hint(array $settings): string {
    $policy = datadock_password_policy($settings);
    $hint = 'At least ' . $policy['min_length'] . ' characters';
    if ($policy['complexity']) {
        $hint .= ', including an uppercase letter, a lowercase letter, and a number';
    }
    return $hint . '.';
}

/**
 * @param array<string, mixed> $settings
 * @return string|null Error message, or null when the password is acceptable
 */
function datadock_password_error(string $password, array $settings): ?string {
    $policy = datadock_password_policy($settings);
    if (strlen($password) < $policy['min_length']) {
        return 'Password must be at least ' . $policy['min_length'] . ' characters.';
    }
    if ($policy['complexity']) {
        $hasLower = (bool) preg_match('/[a-z]/', $password);
        $hasUpper = (bool) preg_match('/[A-Z]/', $password);
        $hasDigit = (bool) preg_match('/[0-9]/', $password);
        if (!$hasLower || !$hasUpper || !$hasDigit) {
            return 'Password must include an uppercase letter, a lowercase letter, and a number.';
        }
    }
    return null;
}

function datadock_count_admins(PDO $pdo, bool $activeOnly = false): int {
    $sql = "SELECT COUNT(*) FROM users WHERE role = 'admin'";
    if ($activeOnly) {
        $sql .= ' AND suspended = 0';
    }
    return (int) $pdo->query($sql)->fetchColumn();
}

/**
 * Permanently remove the signed-in account after the password matches.
 * Files are purged from storage. The last admin cannot be removed.
 *
 * @return string|null Error message, or null on success
 */
function datadock_close_own_account(PDO $pdo, int $userId, string $password): ?string {
    $stmt = $pdo->prepare('SELECT id, username, role, password_hash, avatar FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
        return 'Current password is incorrect.';
    }
    if (($user['role'] ?? '') === 'admin') {
        $others = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND suspended = 0 AND id != ?");
        $others->execute([$userId]);
        if ((int) $others->fetchColumn() < 1) {
            return 'The last active admin account cannot be closed.';
        }
    }

    require_once __DIR__ . '/storage.php';
    require_once __DIR__ . '/audit_log.php';

    $files = $pdo->prepare('SELECT * FROM files WHERE user_id = ?');
    $files->execute([$userId]);
    $rows = $files->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $fileRow) {
        datadock_release_file_storage($pdo, $fileRow);
    }

    $avatar = (string) ($user['avatar'] ?? '');
    if ($avatar !== '' && !preg_match('#^https?://#i', $avatar) && function_exists('get_avatars_path')) {
        $avatarPath = get_avatars_path() . basename($avatar);
        if (is_file($avatarPath)) {
            @unlink($avatarPath);
        }
    }

    datadock_log_activity($pdo, 'account_closed', [
        'actor_user_id' => $userId,
        'detail' => [
            'username' => (string) ($user['username'] ?? ''),
            'files_purged' => count($rows),
        ],
    ]);

    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    return null;
}
