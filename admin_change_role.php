<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_admin();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['role'])) {
    $userId = (int) $_POST['user_id'];
    $newRole = $_POST['role'];

    if (!in_array($newRole, ['user', 'admin'])) {
        exit('Invalid role specified.');
    }

    if ($userId === $_SESSION['user_id']) {
        exit('You cannot change your own role.');
    }

    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$newRole, $userId]);

    $_SESSION['flash_success'] = "User role updated successfully.";
    header("Location: admin.php?section=users");
    exit;
}
http_response_code(400);
echo "Invalid request.";
