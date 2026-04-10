<?php
/**
 * Activity / audit log (DataDock 2.1+).
 */

function datadock_client_ip(): string {
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if (is_string($xff) && $xff !== '') {
        $parts = explode(',', $xff);
        $first = trim($parts[0]);
        if ($first !== '') {
            return substr($first, 0, 45);
        }
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? substr($ip, 0, 45) : '';
}

/**
 * @param array{actor_user_id?:int|null,actor_guest_id?:string|null,file_id?:int|null,related_user_id?:int|null,detail?:array|string|null} $opts
 */
function datadock_log_activity(PDO $pdo, string $action, array $opts = []): void {
    $action = substr(preg_replace('/[^a-z0-9_]/i', '_', $action), 0, 64);
    if ($action === '') {
        $action = 'unknown';
    }
    $actorUser = isset($opts['actor_user_id']) ? (int) $opts['actor_user_id'] : null;
    if ($actorUser !== null && $actorUser <= 0) {
        $actorUser = null;
    }
    $guestId = isset($opts['actor_guest_id']) ? substr((string) $opts['actor_guest_id'], 0, 64) : null;
    if ($guestId === '') {
        $guestId = null;
    }
    $fileId = isset($opts['file_id']) ? (int) $opts['file_id'] : null;
    if ($fileId !== null && $fileId <= 0) {
        $fileId = null;
    }
    $related = isset($opts['related_user_id']) ? (int) $opts['related_user_id'] : null;
    if ($related !== null && $related <= 0) {
        $related = null;
    }
    $detail = $opts['detail'] ?? null;
    $detailJson = null;
    if (is_array($detail)) {
        $detailJson = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif (is_string($detail) && $detail !== '') {
        $detailJson = $detail;
    }
    if ($detailJson !== null && strlen($detailJson) > 65000) {
        $detailJson = substr($detailJson, 0, 65000) . '…';
    }
    $ip = isset($opts['ip']) ? substr((string) $opts['ip'], 0, 45) : datadock_client_ip();

    try {
        $st = $pdo->prepare('
            INSERT INTO activity_log (created_at, action, actor_user_id, actor_guest_id, file_id, related_user_id, detail_json, ip_address)
            VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?, ?)
        ');
        $st->execute([$action, $actorUser, $guestId, $fileId, $related, $detailJson, $ip === '' ? null : $ip]);
    } catch (Throwable $e) {
        // Never break primary flows
    }
}
