<?php
/**
 * Live links to a real folder. The listing follows adds and removes.
 */

function datadock_folder_is_under(PDO $pdo, int $folderId, int $rootId, int $userId): bool {
    $guard = 0;
    while ($folderId > 0 && $guard < 40) {
        if ($folderId === $rootId) {
            return true;
        }
        $stmt = $pdo->prepare('SELECT parent_id FROM folders WHERE id = ? AND user_id = ?');
        $stmt->execute([$folderId, $userId]);
        $parent = $stmt->fetchColumn();
        if ($parent === false) {
            return false;
        }
        $folderId = (int) $parent;
        $guard++;
    }
    return false;
}

function datadock_folder_link_by_token(PDO $pdo, string $token): ?array {
    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
        return null;
    }
    $stmt = $pdo->prepare('
        SELECT fl.*, f.name AS folder_name, f.user_id AS folder_owner
        FROM folder_links fl
        INNER JOIN folders f ON f.id = fl.folder_id
        WHERE fl.token = ?
    ');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $exp = $row['expires_at'] ?? null;
    if ($exp !== null && $exp !== '') {
        $ts = strtotime((string) $exp . ' UTC');
        if ($ts !== false && $ts < time()) {
            return null;
        }
    }
    return $row;
}

function datadock_folder_link_unlocked(int $linkId): bool {
    $bag = $_SESSION['datadock_folder_link'] ?? [];
    return is_array($bag) && !empty($bag[$linkId]);
}

function datadock_folder_link_grant(int $linkId): void {
    if (!isset($_SESSION['datadock_folder_link']) || !is_array($_SESSION['datadock_folder_link'])) {
        $_SESSION['datadock_folder_link'] = [];
    }
    $_SESSION['datadock_folder_link'][$linkId] = true;
}
