<?php
require_once __DIR__ . '/../includes/auth.php';
init_session();
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/accounts.php';
require_once __DIR__ . '/../includes/audit_log.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $userId = (int) $_POST['user_id'];
    $suspend = !empty($_POST['suspended']) ? 1 : 0;

    if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
        $_SESSION['flash_error'][] = '❌ You cannot suspend your own account.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $_SESSION['flash_error'][] = '❌ User not found.';
        } elseif ($suspend === 1 && ($user['role'] ?? '') === 'admin') {
            $othersStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND suspended = 0 AND id != ?");
            $othersStmt->execute([$userId]);
            $others = (int) $othersStmt->fetchColumn();
            if ($others < 1) {
                $_SESSION['flash_error'][] = '❌ The last active admin cannot be suspended.';
            } else {
                $pdo->prepare('UPDATE users SET suspended = 1 WHERE id = ?')->execute([$userId]);
                datadock_log_activity($pdo, 'user_suspended', [
                    'actor_user_id' => (int) $_SESSION['user_id'],
                    'related_user_id' => $userId,
                    'detail' => ['username' => $user['username']],
                ]);
                $_SESSION['flash_success'][] = '✅ ' . $user['username'] . ' is suspended. Login and new shares are blocked.';
            }
        } else {
            $pdo->prepare('UPDATE users SET suspended = ? WHERE id = ?')->execute([$suspend, $userId]);
            datadock_log_activity($pdo, $suspend ? 'user_suspended' : 'user_unsuspended', [
                'actor_user_id' => (int) $_SESSION['user_id'],
                'related_user_id' => $userId,
                'detail' => ['username' => $user['username']],
            ]);
            $_SESSION['flash_success'][] = $suspend
                ? '✅ ' . $user['username'] . ' is suspended. Login and new shares are blocked.'
                : '✅ ' . $user['username'] . ' can log in again.';
        }
    }
}

header('Location: ../admin.php?section=users');
exit;
