<?php
require_once __DIR__ . '/../includes/auth.php';
init_session();
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

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

            $stmt = $pdo->prepare('SELECT * FROM files WHERE user_id = ?');
            $stmt->execute([$userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fileRow) {
                datadock_release_file_storage($pdo, $fileRow);
            }

            // Delete user (cascades files, folders, tags, etc.)
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$userId]);

            $_SESSION['flash_success'][] = "✅ User '$username' deleted successfully.";
        } else {
            $_SESSION['flash_error'][] = "❌ User not found.";
        }
    }
}

header("Location: ../admin.php?section=users");
exit;
