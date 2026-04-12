<?php
/**
 * POST actions for dashboard.php (same-document POST avoids 404s when base URL / SCRIPT_NAME is wrong).
 */

/**
 * Redirect to dashboard with optional folder query; path is relative so it resolves under subdirectories.
 */
function datadock_redirect_dashboard(int $folderId = 0): void {
    $loc = 'dashboard.php';
    if ($folderId > 0) {
        $loc .= '?folder=' . $folderId;
    }
    header('Location: ' . $loc);
    exit;
}

/**
 * Process POST if this is a dashboard action. Call before any output.
 */
function datadock_process_dashboard_post(PDO $pdo): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    require_once __DIR__ . '/settings_loader.php';
    if (datadock_read_only_enabled(datadock_load_settings())) {
        if (!empty($_POST['datadock_folder_create']) || !empty($_POST['datadock_move_file'])) {
            $_SESSION['flash_error'][] = '❌ Read-only mode: folder changes are disabled.';
            $rf = isset($_POST['redirect_folder']) ? (int) $_POST['redirect_folder'] : 0;
            datadock_redirect_dashboard($rf > 0 ? $rf : 0);
        }
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return;
    }
    $isAdmin = ($_SESSION['role'] ?? '') === 'admin';

    if (!empty($_POST['datadock_folder_create'])) {
        $name = trim((string) ($_POST['name'] ?? ''));
        $parentId = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
        $redirectFolder = isset($_POST['redirect_folder']) ? (int) $_POST['redirect_folder'] : $parentId;

        if ($name === '' || strlen($name) > 255) {
            $_SESSION['flash_error'][] = '❌ Invalid folder name.';
            datadock_redirect_dashboard($redirectFolder > 0 ? $redirectFolder : 0);
        }

        if ($parentId > 0) {
            $chk = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ?');
            $chk->execute([$parentId, $userId]);
            if (!$chk->fetch()) {
                $_SESSION['flash_error'][] = '❌ Invalid parent folder.';
                datadock_redirect_dashboard(0);
            }
        }

        try {
            $ins = $pdo->prepare('INSERT INTO folders (user_id, parent_id, name) VALUES (?, ?, ?)');
            $ins->execute([$userId, $parentId, $name]);
            $_SESSION['flash_success'][] = '✅ Folder created.';
        } catch (PDOException $e) {
            $code = (int) ($e->errorInfo[1] ?? 0);
            if ($code === 1062) {
                $_SESSION['flash_error'][] = '❌ A folder with that name already exists here.';
            } else {
                $_SESSION['flash_error'][] = '❌ Could not create folder.';
            }
        }
        datadock_redirect_dashboard($redirectFolder);
    }

    if (!empty($_POST['datadock_move_file'])) {
        $fileId = (int) ($_POST['file_id'] ?? 0);
        $folderId = null;
        if (isset($_POST['folder_id']) && $_POST['folder_id'] !== '') {
            $folderId = (int) $_POST['folder_id'];
            if ($folderId <= 0) {
                $folderId = null;
            }
        }
        $redir = isset($_POST['redirect_folder']) ? (int) $_POST['redirect_folder'] : 0;

        if ($fileId <= 0) {
            $_SESSION['flash_error'][] = '❌ Invalid file.';
            datadock_redirect_dashboard($redir);
        }

        if ($isAdmin) {
            $stmt = $pdo->prepare('SELECT id, user_id FROM files WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$fileId]);
        } else {
            $stmt = $pdo->prepare('SELECT id, user_id FROM files WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
            $stmt->execute([$fileId, $userId]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $_SESSION['flash_error'][] = '❌ File not found or permission denied.';
            datadock_redirect_dashboard($redir);
        }

        $ownerId = (int) $row['user_id'];
        if ($folderId !== null) {
            $chk = $pdo->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ?');
            $chk->execute([$folderId, $ownerId]);
            if (!$chk->fetch()) {
                $_SESSION['flash_error'][] = '❌ Invalid folder.';
                datadock_redirect_dashboard($redir);
            }
        }

        if ($isAdmin) {
            $pdo->prepare('UPDATE files SET folder_id = ? WHERE id = ?')->execute([$folderId, $fileId]);
        } else {
            $pdo->prepare('UPDATE files SET folder_id = ? WHERE id = ? AND user_id = ?')->execute([$folderId, $fileId, $userId]);
        }

        $_SESSION['flash_success'][] = '✅ File location updated.';
        datadock_redirect_dashboard($redir);
    }
}
