<?php
require_once __DIR__ . '/../includes/auth.php';
init_session();
require_admin();
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['role'])) {
    $userId = (int) $_POST['user_id'];
    $newRole = $_POST['role'];

    if (!in_array($newRole, ['user', 'admin'])) {
        $_SESSION['flash_error'][] = '❌ Invalid role specified.';
        header("Location: ../admin.php?section=users");
        exit;
    }

    if ($userId === $_SESSION['user_id']) {
        $_SESSION['flash_error'][] = '❌ You cannot change your own role.';
        header("Location: ../admin.php?section=users");
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$newRole, $userId]);

    $_SESSION['flash_success'][] = '✅ User role updated successfully.';
    header("Location: ../admin.php?section=users");
    exit;
}

http_response_code(400);
echo "Invalid request.";
