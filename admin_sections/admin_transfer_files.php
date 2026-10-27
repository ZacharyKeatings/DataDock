<?php
require_once __DIR__ . '/../includes/auth.php';
init_session();
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ownership.php';
require_once __DIR__ . '/../includes/audit_log.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $source = (int) ($_POST['source_user_id'] ?? 0);
    $target = (int) ($_POST['target_user_id'] ?? 0);
    try {
        $result = datadock_transfer_user_files($pdo, $source, $target);
    } catch (Throwable $e) {
        $result = ['files' => 0, 'error' => 'The transfer stopped. Some files may already have moved.'];
    }
    if ($result['error']) {
        $_SESSION['flash_error'][] = $result['error'];
    } else {
        datadock_log_activity($pdo, 'files_transferred', [
            'actor_user_id' => (int) ($_SESSION['user_id'] ?? 0),
            'detail' => ['from' => $source, 'to' => $target, 'files' => $result['files']],
        ]);
        $_SESSION['flash_success'][] = $result['files'] . ' file(s) now belong to the other account. Pastes moved with them. Folders stayed behind.';
    }
}
header('Location: ../admin.php?section=users');
exit;
