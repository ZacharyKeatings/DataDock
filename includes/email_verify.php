<?php
/**
 * Email verification for new local accounts.
 * SMTP is not wired up yet. datadock_dispatch_verification_email() is the place
 * a mailer will send the link. Until then the signup page shows the link once
 * and an admin can confirm the address from User Management.
 */

function datadock_email_verification_enabled(array $settings): bool {
    return !empty($settings['email_verification_enabled']);
}

function datadock_mail_transport_ready(): bool {
    return false;
}

function datadock_issue_email_verification(PDO $pdo, int $userId): string {
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE users SET email_verified = 0, email_verify_token = ?, email_verify_expires = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 DAY) WHERE id = ?')
        ->execute([$token, $userId]);
    return $token;
}

function datadock_verification_url(string $token): string {
    return 'verify_email.php?token=' . rawurlencode($token);
}

/**
 * @return array{sent:bool,reason:string,token:string}
 */
function datadock_dispatch_verification_email(string $email, string $token): array {
    unset($email);
    if (!datadock_mail_transport_ready()) {
        return ['sent' => false, 'reason' => 'smtp_not_configured', 'token' => $token];
    }
    return ['sent' => false, 'reason' => 'smtp_not_configured', 'token' => $token];
}

/**
 * @return array{sent:bool,reason:string,url:string}
 */
function datadock_begin_email_verification(PDO $pdo, int $userId, string $email): array {
    $token = datadock_issue_email_verification($pdo, $userId);
    $dispatch = datadock_dispatch_verification_email($email, $token);
    return [
        'sent' => $dispatch['sent'],
        'reason' => $dispatch['reason'],
        'url' => datadock_verification_url($token),
    ];
}

function datadock_login_blocked_for_email(array $user, array $settings): bool {
    if (!datadock_email_verification_enabled($settings)) {
        return false;
    }
    if (!array_key_exists('email_verified', $user)) {
        return false;
    }
    return (int) $user['email_verified'] === 0;
}

function datadock_confirm_email_token(PDO $pdo, string $token): bool {
    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email_verify_token = ? AND email_verify_expires > UTC_TIMESTAMP()');
    $stmt->execute([$token]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        return false;
    }
    $pdo->prepare('UPDATE users SET email_verified = 1, email_verify_token = NULL, email_verify_expires = NULL WHERE id = ?')
        ->execute([(int) $id]);
    return true;
}
