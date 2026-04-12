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

$userId = (int) $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

$linkExpiryOptions = [
    '1_hour' => '+1 hour',
    '1_day' => '+1 day',
    '7_days' => '+7 days',
    '30_days' => '+30 days',
    'never' => null,
];

$ids = [];
if (isset($_GET['ids']) && is_array($_GET['ids'])) {
    foreach ($_GET['ids'] as $id) {
        if (is_numeric($id)) {
            $ids[] = (int) $id;
        }
    }
}
$ids = array_values(array_unique(array_filter($ids)));

if (empty($ids)) {
    $_SESSION['flash_error'][] = '❌ Select at least one file on the dashboard (use checkboxes), then choose “Create share folder link”.';
    header('Location: dashboard.php');
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id IN ($placeholders) AND deleted_at IS NULL AND (quarantine_status = 'approved' OR quarantine_status IS NULL)");
    $stmt->execute($ids);
} else {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id IN ($placeholders) AND user_id = ? AND deleted_at IS NULL AND (quarantine_status = 'approved' OR quarantine_status IS NULL)");
    $stmt->execute(array_merge($ids, [$userId]));
}
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($files) !== count($ids)) {
    $_SESSION['flash_error'][] = '❌ Some files were not found or you do not have permission to include them.';
    header('Location: dashboard.php');
    exit;
}

$byId = [];
foreach ($files as $f) {
    $byId[(int) $f['id']] = $f;
}
$orderedFiles = [];
foreach ($ids as $iid) {
    if (isset($byId[$iid])) {
        $orderedFiles[] = $byId[$iid];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    if (strlen($title) > 200) {
        $title = substr($title, 0, 200);
    }
    $linkKey = $_POST['link_expires'] ?? '7_days';
    if (!isset($linkExpiryOptions[$linkKey])) {
        $linkKey = '7_days';
    }
    $expiresAt = null;
    if ($linkExpiryOptions[$linkKey] !== null) {
        $nowUTC = new DateTime('now', new DateTimeZone('UTC'));
        $expiresAt = (clone $nowUTC)->modify($linkExpiryOptions[$linkKey])->format('Y-m-d H:i:s');
    }

    $notesIn = $_POST['recipient_note'] ?? [];
    if (!is_array($notesIn)) {
        $notesIn = [];
    }

    $token = bin2hex(random_bytes(32));
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare('INSERT INTO share_folders (user_id, token, title, expires_at) VALUES (?, ?, ?, ?)');
        $ins->execute([$userId, $token, $title !== '' ? $title : null, $expiresAt]);
        $sfId = (int) $pdo->lastInsertId();
        $sort = 0;
        $insF = $pdo->prepare('INSERT INTO share_folder_files (share_folder_id, file_id, recipient_note, sort_order) VALUES (?, ?, ?, ?)');
        foreach ($orderedFiles as $f) {
            $fid = (int) $f['id'];
            $note = isset($notesIn[$fid]) ? trim((string) $notesIn[$fid]) : '';
            if (strlen($note) > 500) {
                $note = substr($note, 0, 500);
            }
            $insF->execute([$sfId, $fid, $note !== '' ? $note : null, $sort]);
            $sort++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $_SESSION['flash_error'][] = '❌ Could not create share folder.';
        header('Location: create_share_folder.php?' . http_build_query(['ids' => $ids]));
        exit;
    }

    datadock_log_activity($pdo, 'share_folder_created', [
        'actor_user_id' => $userId,
        'detail' => ['share_folder_id' => $sfId, 'file_count' => count($orderedFiles), 'expires' => $expiresAt],
    ]);

    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
    $shareUrl = $baseUrl . rtrim($scriptDir, '/') . '/share_folder.php?t=' . urlencode($token);

    $pageTitle = 'Share folder link';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div class="page-section">
        <h2 class="page-title">Share folder link</h2>
        <p>Anyone with this link can view the list and download files (subject to each file’s IP and password rules, if any).</p>
        <p><input type="text" readonly class="form-input" id="share-folder-url" style="width:100%;max-width:48rem;" value="<?= sanitize_data($shareUrl) ?>"></p>
        <p><button type="button" class="btn btn-primary" onclick="var u=document.getElementById('share-folder-url');if(u&&navigator.clipboard)navigator.clipboard.writeText(u.value);this.textContent='Copied!';">Copy link</button></p>
        <p><a href="dashboard.php" class="btn btn-small">Back to dashboard</a></p>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = 'Create share folder link';
require_once __DIR__ . '/includes/header.php';
?>
<div class="page-section">
    <h2 class="page-title">Create share folder link</h2>
    <p>One link for <?= (int) count($orderedFiles) ?> file(s). Optional note per file is shown to recipients only.</p>
    <form method="post" class="form">
        <div class="settings-row">
            <label for="title">Title (optional)</label>
            <input type="text" name="title" id="title" maxlength="200" placeholder="e.g. Project deliverables" style="max-width:32rem;">
        </div>
        <div class="settings-row">
            <label for="link_expires">Link expires</label>
            <select name="link_expires" id="link_expires">
                <?php foreach ($linkExpiryOptions as $k => $_): ?>
                    <option value="<?= sanitize_data($k) ?>"<?= $k === '7_days' ? ' selected' : '' ?>><?= sanitize_data(str_replace('_', ' ', $k)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <table class="file-list" style="width:100%;border-collapse:collapse;margin-top:1rem;">
            <thead>
                <tr>
                    <th style="text-align:left;padding:0.35rem;">File</th>
                    <th style="text-align:left;padding:0.35rem;">Recipient note (optional)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orderedFiles as $f): $fid = (int) $f['id']; ?>
                    <tr>
                        <td style="padding:0.35rem;vertical-align:top;"><?= sanitize_data($f['original_name'] ?? $f['filename']) ?></td>
                        <td style="padding:0.35rem;">
                            <textarea name="recipient_note[<?= $fid ?>]" rows="2" maxlength="500" style="width:100%;max-width:28rem;" placeholder="Visible only on the share page"></textarea>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top:1rem;">
            <button type="submit" class="btn btn-primary">Create link</button>
            <a href="dashboard.php" class="btn btn-small">Cancel</a>
        </p>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
