<?php
require_once __DIR__ . '/../includes/auth.php';
init_session();
require_admin();
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $userId = (int) $_POST['user_id'];
    $partitionRaw = $_POST['storage_partition_id'] ?? '';
    $partitionId = $partitionRaw === '' ? null : (int) $partitionRaw;

    if ($partitionId !== null && $partitionId <= 0) {
        $partitionId = null;
    }

    if ($partitionId !== null) {
        $chk = $pdo->prepare('SELECT id FROM storage_partitions WHERE id = ?');
        $chk->execute([$partitionId]);
        if (!$chk->fetch()) {
            $_SESSION['flash_error'][] = '❌ Invalid storage partition.';
            header('Location: ../admin.php?section=users');
            exit;
        }
    }

    $stmt = $pdo->prepare('UPDATE users SET storage_partition_id = ? WHERE id = ?');
    $stmt->execute([$partitionId, $userId]);

    $_SESSION['flash_success'][] = '✅ Storage partition updated for user.';
    header('Location: ../admin.php?section=users');
    exit;
}

http_response_code(400);
echo 'Invalid request.';
