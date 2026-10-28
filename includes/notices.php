<?php
/**
 * In-app notices. Email delivery can reuse the same events later.
 */

function datadock_add_notice(PDO $pdo, int $userId, string $kind, string $title, ?string $body = null, ?string $link = null): void {
    if ($userId <= 0 || $title === '') {
        return;
    }
    $kind = substr(preg_replace('/[^a-z0-9_]/i', '', $kind) ?? '', 0, 32);
    if ($kind === '') {
        $kind = 'notice';
    }
    try {
        $stmt = $pdo->prepare('
            INSERT INTO user_notices (user_id, kind, title, body, link_url)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $userId,
            $kind,
            substr($title, 0, 200),
            $body !== null && $body !== '' ? substr($body, 0, 1000) : null,
            $link !== null && $link !== '' ? substr($link, 0, 500) : null,
        ]);
    } catch (Throwable $e) {
    }
}

function datadock_add_notice_daily(PDO $pdo, int $userId, string $kind, string $title, ?string $body = null, ?string $link = null): void {
    if ($userId <= 0) {
        return;
    }
    try {
        $stmt = $pdo->prepare('
            SELECT id FROM user_notices
            WHERE user_id = ? AND kind = ? AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)
            LIMIT 1
        ');
        $stmt->execute([$userId, substr($kind, 0, 32)]);
        if ($stmt->fetch()) {
            return;
        }
    } catch (Throwable $e) {
        return;
    }
    datadock_add_notice($pdo, $userId, $kind, $title, $body, $link);
}

function datadock_notice_unread_count(PDO $pdo, int $userId): int {
    if ($userId <= 0) {
        return 0;
    }
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_notices WHERE user_id = ? AND read_at IS NULL');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * @return list<array<string, mixed>>
 */
function datadock_count_notices(PDO $pdo, int $userId): int {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_notices WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function datadock_list_notices(PDO $pdo, int $userId, int $limit = 50, int $offset = 0): array {
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    try {
        $stmt = $pdo->prepare('SELECT * FROM user_notices WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function datadock_mark_notices_read(PDO $pdo, int $userId): void {
    if ($userId <= 0) {
        return;
    }
    try {
        $pdo->prepare('UPDATE user_notices SET read_at = UTC_TIMESTAMP() WHERE user_id = ? AND read_at IS NULL')->execute([$userId]);
    } catch (Throwable $e) {
    }
}

/**
 * Dashboard-side notices for quota and files expiring within seven days.
 *
 * @param array<string, mixed> $settings
 */
function datadock_refresh_account_notices(PDO $pdo, int $userId, array $settings, int $expiringCount, int $storagePercent, bool $quotaEnabled): void {
    if ($expiringCount > 0) {
        datadock_add_notice_daily(
            $pdo,
            $userId,
            'expiring_soon',
            $expiringCount === 1 ? '1 file expires within 7 days' : $expiringCount . ' files expire within 7 days',
            'Open your files to download or change the expiry before they are removed.',
            'dashboard.php'
        );
    }
    if (!$quotaEnabled) {
        return;
    }
    $ops = is_array($settings['ops_alerts'] ?? null) ? $settings['ops_alerts'] : [];
    $threshold = (int) ($ops['user_quota_percent_threshold'] ?? 90);
    if ($threshold < 1) {
        $threshold = 90;
    }
    if ($storagePercent >= $threshold) {
        datadock_add_notice_daily(
            $pdo,
            $userId,
            'quota_warning',
            'Storage is ' . $storagePercent . '% full',
            'Delete files or ask an admin to raise the quota before uploads start failing.',
            'dashboard.php'
        );
    }
}
