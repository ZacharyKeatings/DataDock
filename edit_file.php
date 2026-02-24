<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/settings.php';

$autoDeleteDurations = [
    '1_minute'   => '+1 minute',
    '30_minutes' => '+30 minutes',
    '1_hour'     => '+1 hour',
    '6_hours'    => '+6 hours',
    '1_day'      => '+1 day',
    '1_week'     => '+1 week',
    '1_month'    => '+1 month',
    '1_year'     => '+1 year',
    'never'      => null,
];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_error'][] = "❌ Invalid file.";
    header("Location: dashboard.php");
    exit;
}

$fileId = (int) $_GET['id'];
$userId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

if ($isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$fileId]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ? AND deleted_at IS NULL");
    $stmt->execute([$fileId, $userId]);
}
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    $_SESSION['flash_error'][] = "❌ File not found or permission denied.";
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $originalName = trim($_POST['original_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = $_POST['duration'] ?? 'never';

    if ($originalName === '') {
        $_SESSION['flash_error'][] = "❌ Filename cannot be empty.";
    } else {
        if (strlen($originalName) > 255) {
            $_SESSION['flash_error'][] = "❌ Filename is too long.";
        }
        if (strlen($description) > 500) {
            $_SESSION['flash_error'][] = "❌ Description is too long (max 500 characters).";
        }
    }

    if ($duration === 'keep' || !isset($autoDeleteDurations[$duration])) {
        if ($duration !== 'keep') {
            $duration = 'never';
        }
    }

    $expiryDate = null;
    if ($duration === 'keep') {
        $expiryDate = $file['expiry_date'];
    } elseif ($autoDeleteDurations[$duration] !== null) {
        $nowUTC = new DateTime('now', new DateTimeZone('UTC'));
        $expiryDate = (clone $nowUTC)->modify($autoDeleteDurations[$duration])->format('Y-m-d H:i:s');
    }

    if (empty($_SESSION['flash_error'])) {
        if ($isAdmin) {
            $stmt = $pdo->prepare("UPDATE files SET original_name = ?, description = ?, expiry_date = ? WHERE id = ?");
            $stmt->execute([$originalName, $description === '' ? null : $description, $expiryDate, $fileId]);
        } else {
            $stmt = $pdo->prepare("UPDATE files SET original_name = ?, description = ?, expiry_date = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$originalName, $description === '' ? null : $description, $expiryDate, $fileId, $userId]);
        }
        $_SESSION['flash_success'][] = "✅ File metadata updated.";
        header("Location: dashboard.php");
        exit;
    }
}

$pageTitle = "Edit file";
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section">
    <h2 class="page-title">Edit file</h2>
    <p class="page-description">Change display name, description, or expiry. The file on disk is not modified.</p>

    <form method="post" action="edit_file.php?id=<?= (int)$fileId ?>" class="settings-card" style="max-width:32rem;">
        <div class="settings-card-body">
            <div class="form-group">
                <label for="original_name">Display name</label>
                <input type="text" name="original_name" id="original_name" value="<?= sanitize_data($file['original_name'] ?? '') ?>" maxlength="255" required>
            </div>
            <div class="form-group">
                <label for="description">Description <span class="label-optional">(optional, max 500 characters)</span></label>
                <textarea name="description" id="description" rows="3" maxlength="500"><?= sanitize_data($file['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="duration">Expires</label>
                <select name="duration" id="duration">
                    <option value="keep"<?= $file['expiry_date'] !== null ? ' selected' : '' ?>>Keep current</option>
                    <?php foreach ($autoDeleteDurations as $key => $offset): ?>
                        <option value="<?= sanitize_data($key) ?>"<?= $file['expiry_date'] === null && $key === 'never' ? ' selected' : '' ?>>
                            <?= ucwords(str_replace('_', ' ', $key)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($file['expiry_date']): ?>
                    <small class="form-hint">Current expiry: <span class="utc-datetime" data-utc="<?= sanitize_data($file['expiry_date']) ?>"></span></small>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="dashboard.php" class="btn btn-small">Cancel</a>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.utc-datetime').forEach(el => {
        const utc = el.dataset.utc;
        if (utc) {
            const local = new Date(utc + ' UTC');
            el.textContent = local.toLocaleString();
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
