<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_error'][] = "❌ Invalid file.";
    header("Location: dashboard.php");
    exit;
}

$fileId = (int) $_GET['id'];
$userId = $_SESSION['user_id'];

// Verify ownership
$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ? AND deleted_at IS NULL AND (quarantine_status = 'approved' OR quarantine_status IS NULL)");
$stmt->execute([$fileId, $userId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    $_SESSION['flash_error'][] = "❌ File not found or permission denied.";
    header("Location: dashboard.php");
    exit;
}

// Handle POST - add or remove share
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');

    if ($action === 'add' && strlen($username) >= 2) {
        // Don't share with self
        if (strtolower($username) === strtolower($_SESSION['username'])) {
            $_SESSION['flash_error'][] = "❌ You cannot share with yourself.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$targetUser) {
                $_SESSION['flash_error'][] = "❌ User « " . sanitize_data($username) . " » not found.";
            } else {
                try {
                    $ins = $pdo->prepare("INSERT INTO file_shares (file_id, shared_with_user_id, shared_by_user_id) VALUES (?, ?, ?)");
                    $ins->execute([$fileId, $targetUser['id'], $userId]);
                    $_SESSION['flash_success'][] = "✅ File shared with " . sanitize_data($username) . ".";
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) { // duplicate
                        $_SESSION['flash_error'][] = "❌ Already shared with " . sanitize_data($username) . ".";
                    } else {
                        $_SESSION['flash_error'][] = "❌ Failed to add share.";
                    }
                }
            }
        }
    } elseif ($action === 'remove' && isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
        $removeUserId = (int) $_POST['user_id'];
        $stmt = $pdo->prepare("DELETE FROM file_shares WHERE file_id = ? AND shared_with_user_id = ? AND shared_by_user_id = ?");
        $stmt->execute([$fileId, $removeUserId, $userId]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['flash_success'][] = "✅ Share removed.";
        }
    }

    header("Location: share.php?id=" . $fileId);
    exit;
}

// Fetch current shares
$stmt = $pdo->prepare("
    SELECT fs.id, fs.shared_with_user_id, u.username
    FROM file_shares fs
    JOIN users u ON fs.shared_with_user_id = u.id
    WHERE fs.file_id = ? AND fs.shared_by_user_id = ?
    ORDER BY u.username
");
$stmt->execute([$fileId, $userId]);
$shares = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Share: " . $file['original_name'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section">
    <h2 class="page-title">Share File</h2>
    <p><strong>File:</strong> <?= sanitize_data($file['original_name']) ?></p>
    <p><a href="dashboard.php" class="btn btn-small">← Back to Your Files</a></p>

    <h3>Add share</h3>
    <form method="post" class="form" style="display:flex;gap:0.5rem;align-items:center;margin-bottom:1.5rem;">
        <input type="hidden" name="action" value="add">
        <input type="text" name="username" placeholder="Username" required minlength="2" autocomplete="off">
        <button type="submit" class="btn btn-primary">Share with user</button>
    </form>

    <h3>Shared with</h3>
    <?php if ($shares): ?>
        <ul class="share-list">
            <?php foreach ($shares as $s): ?>
            <li>
                <?= user_profile_link($s['username']) ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('Remove share with <?= htmlspecialchars($s['username']) ?>?')">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="user_id" value="<?= (int)$s['shared_with_user_id'] ?>">
                    <button type="submit" class="btn btn-small btn-danger">Remove</button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Not shared with anyone yet.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
