<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/audit_log.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$reasons = [
    'malicious' => 'Malicious content',
    'inappropriate' => 'Inappropriate content',
    'copyright' => 'Possible copyright violation',
    'other' => 'Other policy concern',
];

function datadock_safe_report_return(string $returnTo): string {
    $returnTo = trim($returnTo);
    if ($returnTo === '') {
        return 'dashboard.php';
    }
    if (str_contains($returnTo, '://') || str_starts_with($returnTo, '//')) {
        return 'dashboard.php';
    }
    if (str_starts_with($returnTo, '/')) {
        return 'dashboard.php';
    }
    if (!preg_match('/^[a-zA-Z0-9_\-\.\/\?\=\&%]+$/', $returnTo)) {
        return 'dashboard.php';
    }
    return $returnTo;
}

function datadock_get_reportable_file(PDO $pdo, int $fileId, int $userId): ?array {
    $stmt = $pdo->prepare("
        SELECT f.id, f.user_id, f.original_name, f.filename, f.is_public,
               fs.id AS shared_match
        FROM files f
        LEFT JOIN file_shares fs ON fs.file_id = f.id AND fs.shared_with_user_id = ?
        WHERE f.id = ?
          AND f.deleted_at IS NULL
          AND (f.expiry_date IS NULL OR f.expiry_date > UTC_TIMESTAMP())
          AND (f.quarantine_status = 'approved' OR f.quarantine_status IS NULL)
        LIMIT 1
    ");
    $stmt->execute([$userId, $fileId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $isOwner = (int) ($row['user_id'] ?? 0) === $userId;
    $isShared = !empty($row['shared_match']);
    $isPublic = !empty($row['is_public']);
    if ($isOwner || (!$isShared && !$isPublic)) {
        return null;
    }
    return $row;
}

$fileId = isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
if ($fileId <= 0) {
    $_SESSION['flash_error'][] = '❌ Invalid file report request.';
    header('Location: dashboard.php');
    exit;
}

$returnTo = datadock_safe_report_return((string) ($_REQUEST['return_to'] ?? 'dashboard.php'));
$file = datadock_get_reportable_file($pdo, $fileId, $userId);
if (!$file) {
    $_SESSION['flash_error'][] = '❌ File not found, not reportable, or permission denied.';
    header('Location: ' . $returnTo);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $reason = (string) ($_POST['reason'] ?? 'malicious');
    $details = trim((string) ($_POST['details'] ?? ''));
    if (!isset($reasons[$reason])) {
        $reason = 'other';
    }
    if (strlen($details) > 1000) {
        $details = substr($details, 0, 1000);
    }

    $dup = $pdo->prepare('SELECT id FROM file_reports WHERE file_id = ? AND reporter_user_id = ? AND status = \'open\' LIMIT 1');
    $dup->execute([$fileId, $userId]);
    if ($dup->fetchColumn()) {
        $_SESSION['flash_warning'][] = '⚠️ You already have an open report for this file.';
        header('Location: ' . $returnTo);
        exit;
    }

    $ins = $pdo->prepare('
        INSERT INTO file_reports (created_at, file_id, reporter_user_id, reason, details, status)
        VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, \'open\')
    ');
    $ins->execute([$fileId, $userId, $reason, $details !== '' ? $details : null]);

    datadock_log_activity($pdo, 'report_file', [
        'actor_user_id' => $userId,
        'file_id' => $fileId,
        'related_user_id' => (int) ($file['user_id'] ?? 0),
        'detail' => [
            'reason' => $reason,
            'name' => $file['original_name'] ?? $file['filename'] ?? '',
        ],
    ]);

    $_SESSION['flash_success'][] = '✅ Report submitted. An admin will review it.';
    header('Location: ' . $returnTo);
    exit;
}

$pageTitle = 'Report file';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section" style="max-width:42rem;">
    <h2 class="page-title">Report file</h2>
    <p class="page-description">
        Report <code><?= sanitize_data($file['original_name'] ?? $file['filename'] ?? ('#' . $fileId)) ?></code> for moderation review.
    </p>

    <form method="post" action="report_file.php" class="settings-form">
        <input type="hidden" name="id" value="<?= (int) $fileId ?>">
        <input type="hidden" name="return_to" value="<?= sanitize_data($returnTo) ?>">

        <div class="settings-card">
            <h3 class="settings-card-title">Reason</h3>
            <div class="settings-group">
                <label for="report-reason">Choose reason</label>
                <select id="report-reason" name="reason" required>
                    <?php foreach ($reasons as $value => $label): ?>
                        <option value="<?= sanitize_data($value) ?>"><?= sanitize_data($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="settings-group">
                <label for="report-details">Details (optional)</label>
                <textarea id="report-details" name="details" maxlength="1000" rows="5" placeholder="Add context for admins reviewing this report."></textarea>
            </div>
        </div>

        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">Submit report</button>
            <a href="<?= sanitize_data($returnTo) ?>" class="btn btn-small">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
