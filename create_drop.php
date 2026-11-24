<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
require_once __DIR__ . '/includes/read_only.php';
require_once __DIR__ . '/includes/personal.php';

$settings = datadock_load_settings();
$userId = (int) $_SESSION['user_id'];
$folderId = (int) ($_GET['folder'] ?? $_POST['folder_id'] ?? 0);
$back = 'dashboard.php?folder=' . $folderId;
datadock_block_if_read_only($settings, $back, $back);

$stmt = $pdo->prepare('SELECT id, name FROM folders WHERE id = ? AND user_id = ?');
$stmt->execute([$folderId, $userId]);
$folder = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$folder) {
    $_SESSION['flash_error'][] = 'Choose one of your folders first.';
    header('Location: dashboard.php');
    exit;
}

$created = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    datadock_reject_invalid_csrf('create_drop.php?folder=' . $folderId);
    if (isset($_POST['revoke_id'])) {
        $pdo->prepare('DELETE FROM drop_links WHERE id = ? AND user_id = ? AND folder_id = ?')->execute([
            (int) $_POST['revoke_id'],
            $userId,
            $folderId,
        ]);
        $_SESSION['flash_success'][] = 'Drop link removed.';
        header('Location: create_drop.php?folder=' . $folderId);
        exit;
    }
    $token = datadock_create_drop_link($pdo, $userId, $folderId);
    if ($token === null) {
        $_SESSION['flash_error'][] = 'Could not create a drop link.';
    } else {
        $created = datadock_public_url('drop.php?t=' . $token);
    }
}

$list = $pdo->prepare('SELECT id, token, created_at FROM drop_links WHERE user_id = ? AND folder_id = ? ORDER BY id DESC');
$list->execute([$userId, $folderId]);
$links = $list->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Drop link';
require __DIR__ . '/includes/header.php';
?>
<div class="page-section">
    <h2 class="page-title">Drop link</h2>
    <p class="page-description">Anyone with this link can add files to <strong><?= sanitize_data((string) $folder['name']) ?></strong>. The files count against your quota and use this folder’s expiry and visibility. The sender does not need an account.</p>
    <?php if ($created !== ''): ?>
        <div class="onetimelink-box">
            <label for="dropUrl" class="visually-hidden">Drop link</label>
            <input type="text" id="dropUrl" class="onetimelink-input" readonly value="<?= sanitize_data($created) ?>" onclick="this.select();">
        </div>
    <?php endif; ?>
    <form method="post" action="create_drop.php?folder=<?= (int) $folderId ?>" class="form">
        <?= datadock_csrf_field() ?>
        <input type="hidden" name="folder_id" value="<?= (int) $folderId ?>">
        <button type="submit" class="btn btn-primary">Create drop link</button>
        <a href="<?= sanitize_data($back) ?>" class="btn btn-quiet">Back</a>
    </form>
    <?php if ($links): ?>
        <ul class="notice-list">
            <?php foreach ($links as $link): ?>
                <?php $url = datadock_public_url('drop.php?t=' . $link['token']); ?>
                <li class="notice-item">
                    <input type="text" readonly class="paste-url" value="<?= sanitize_data($url) ?>" onclick="this.select();">
                    <form method="post" action="create_drop.php?folder=<?= (int) $folderId ?>" class="inline-form">
                        <?= datadock_csrf_field() ?>
                        <input type="hidden" name="folder_id" value="<?= (int) $folderId ?>">
                        <input type="hidden" name="revoke_id" value="<?= (int) $link['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-small">Remove</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
