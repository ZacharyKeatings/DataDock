<?php
require_once __DIR__ . '/../includes/auth.php';
init_session();
require_admin();
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $userId = (int) $_POST['user_id'];

    // Prevent deleting yourself
    if ($userId === $_SESSION['user_id']) {
        $_SESSION['flash_error'][] = "❌ You cannot delete your own admin account.";
    } else {
        // Get username before deletion
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $username = $user['username'];

            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);

            $_SESSION['flash_success'][] = "✅ User '$username' deleted successfully.";
        } else {
            $_SESSION['flash_error'][] = "❌ User not found.";
        }
    }
}

header("Location: ../admin.php?section=users");
exit;
