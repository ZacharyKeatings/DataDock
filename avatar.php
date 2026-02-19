<?php
/**
 * Serve user avatar. ?username=xyz or ?id=123
 * If user's avatar is a URL, redirect. If uploaded file, stream from avatars dir.
 */
require_once __DIR__ . '/includes/auth.php';
init_session();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$userId = null;
if (isset($_GET['username']) && trim($_GET['username']) !== '') {
    $stmt = $pdo->prepare("SELECT id, avatar FROM users WHERE username = ?");
    $stmt->execute([trim($_GET['username'])]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $userId = $row['id'];
        $avatar = $row['avatar'];
    }
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT id, avatar FROM users WHERE id = ?");
    $stmt->execute([(int) $_GET['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $userId = $row['id'];
        $avatar = $row['avatar'];
    }
}

if ($userId === null || $avatar === null || trim($avatar) === '') {
    http_response_code(404);
    exit;
}

$avatar = trim($avatar);

if (preg_match('#^https?://#i', $avatar)) {
    header('Location: ' . $avatar);
    exit;
}

$filename = basename($avatar);
if ($filename === '' || strpos($avatar, '..') !== false) {
    http_response_code(400);
    exit;
}

$path = get_avatars_path() . $filename;
if (!file_exists($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
$mime = $mimes[$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=86400');
readfile($path);
exit;
