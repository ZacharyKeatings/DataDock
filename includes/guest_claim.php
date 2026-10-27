<?php
/**
 * One-time codes that attach guest uploads to an account.
 */

function datadock_issue_guest_claim(PDO $pdo, string $guestId): ?string {
    if (!preg_match('/^[a-f0-9]{32}$/', $guestId)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT token FROM guest_claims WHERE guest_id = ? AND claimed_at IS NULL AND expires_at > UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1');
    $stmt->execute([$guestId]);
    $existing = $stmt->fetchColumn();
    if (is_string($existing) && strlen($existing) === 32) {
        return $existing;
    }
    $token = bin2hex(random_bytes(16));
    $ins = $pdo->prepare('INSERT INTO guest_claims (token, guest_id, expires_at) VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL 30 DAY)');
    $ins->execute([$token, $guestId]);
    return $token;
}

/**
 * Attach matching guest files and pastes when the account can hold them.
 * The code stays unused when the quota would be exceeded.
 *
 * @return array{ok:bool,message:string,files:int}
 */
function datadock_claim_guest_uploads(PDO $pdo, array $settings, int $userId, string $token): array {
    $token = strtolower(trim($token));
    if ($token === '') {
        return ['ok' => true, 'message' => '', 'files' => 0];
    }
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return ['ok' => false, 'message' => 'That claim code is not valid.', 'files' => 0];
    }
    $stmt = $pdo->prepare('SELECT * FROM guest_claims WHERE token = ? AND claimed_at IS NULL AND expires_at > UTC_TIMESTAMP()');
    $stmt->execute([$token]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$claim) {
        return ['ok' => false, 'message' => 'That claim code is missing, expired, or already used.', 'files' => 0];
    }
    $guestId = (string) $claim['guest_id'];
    $files = $pdo->prepare('SELECT * FROM files WHERE guest_id = ? AND user_id IS NULL AND deleted_at IS NULL');
    $files->execute([$guestId]);
    $rows = $files->fetchAll(PDO::FETCH_ASSOC);
    $addBytes = 0;
    foreach ($rows as $row) {
        $addBytes += (int) ($row['filesize'] ?? 0);
    }
    if (!function_exists('datadock_user_quota_error')) {
        require_once __DIR__ . '/quotas.php';
    }
    $quotaError = datadock_user_quota_error($pdo, $userId, $settings, count($rows), $addBytes);
    if ($quotaError !== null) {
        return ['ok' => false, 'message' => $quotaError . ' The claim code was not used.', 'files' => 0];
    }
    $targetPartition = datadock_resolve_user_partition_id($pdo, $userId);
    require_once __DIR__ . '/ownership.php';
    foreach ($rows as $row) {
        datadock_move_file_to_user($pdo, $row, $userId, $targetPartition);
    }
    $pdo->prepare('UPDATE pastes SET user_id = ?, guest_id = NULL WHERE guest_id = ? AND user_id IS NULL')->execute([$userId, $guestId]);
    $pdo->prepare('UPDATE guest_claims SET claimed_at = UTC_TIMESTAMP(), claimed_by = ? WHERE id = ?')->execute([$userId, (int) $claim['id']]);
    $n = count($rows);
    $message = $n === 1 ? '1 guest upload is now on your account.' : ($n . ' guest uploads are now on your account.');
    return ['ok' => true, 'message' => $message, 'files' => $n];
}
