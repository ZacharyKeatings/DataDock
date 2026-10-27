<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings_loader.php';
$settings = datadock_load_settings();
require_once __DIR__ . '/includes/read_only.php';
datadock_block_if_read_only($settings);
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/access_control.php';

$folderId = (int) ($_GET['folder'] ?? $_POST['folder_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM folders WHERE id = ? AND user_id = ?');
$stmt->execute([$folderId, $userId]);
$folder = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$folder) {
    $_SESSION['flash_error'][] = 'Folder not found.';
    header('Location: dashboard.php');
    exit;
}

$createdUrl = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    datadock_reject_invalid_csrf('create_folder_link.php?folder=' . $folderId);
    $options = [
        '1_hour' => '+1 hour',
        '1_day' => '+1 day',
        '7_days' => '+7 days',
        '30_days' => '+30 days',
        'never' => null,
    ];
    $key = (string) ($_POST['link_expires'] ?? '7_days');
    if (!isset($options[$key])) {
        $key = '7_days';
    }
    $expires = null;
    if ($options[$key] !== null) {
        $expires = (new DateTime('now', new DateTimeZone('UTC')))->modify($options[$key])->format('Y-m-d H:i:s');
    }
    $password = (string) ($_POST['access_password'] ?? '');
    $hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
    $allow = trim((string) ($_POST['ip_allowlist'] ?? ''));
    $allowJson = $allow === '' ? null : datadock_parse_ip_allowlist_text($allow);
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO folder_links (folder_id, user_id, token, expires_at, access_password_hash, ip_allowlist) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        $folderId, $userId, $token, $expires, $hash, $allowJson,
    ]);
    datadock_log_activity($pdo, 'folder_link_created', [
        'actor_user_id' => $userId,
        'detail' => ['folder_id' => $folderId, 'name' => $folder['name']],
    ]);
    $createdUrl = datadock_public_url('folder_link.php') . '?t=' . $token;
}

$pageTitle = 'Folder link';
require __DIR__ . '/includes/header.php';
?>
<div class="page-section">
    <h2 class="page-title">Live folder link</h2>
    <p class="page-description">This link lists the current contents of <strong><?= sanitize_data($folder['name']) ?></strong>. Files you add or remove later show up on the same link. It is separate from a snapshot share bundle.</p>
    <?php if ($createdUrl !== ''): ?>
        <div class="onetimelink-box"><code><?= sanitize_data($createdUrl) ?></code></div>
    <?php endif; ?>
    <form method="post" class="form">
        <?= datadock_csrf_field() ?>
        <input type="hidden" name="folder_id" value="<?= (int) $folderId ?>">
        <div class="form-group">
            <label for="link_expires">Expires</label>
            <select name="link_expires" id="link_expires">
                <option value="1_hour">1 hour</option>
                <option value="1_day">1 day</option>
                <option value="7_days" selected>7 days</option>
                <option value="30_days">30 days</option>
                <option value="never">Never</option>
            </select>
        </div>
        <div class="form-group">
            <label for="access_password">Password <span class="paste-optional">(optional)</span></label>
            <input type="password" name="access_password" id="access_password" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="ip_allowlist">IP allow list <span class="paste-optional">(optional)</span></label>
            <textarea name="ip_allowlist" id="ip_allowlist" rows="3" placeholder="One IP or CIDR per line"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Create link</button>
        <a href="dashboard.php?folder=<?= (int) $folderId ?>" class="btn btn-quiet">Back</a>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
