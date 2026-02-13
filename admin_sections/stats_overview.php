<?php
// Site stats overview
$stmt = $pdo->query("SELECT COUNT(*) FROM files");
$totalUploads = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(filesize), 0) FROM files");
$totalStorage = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$adminCount = (int) $stmt->fetchColumn();

// File type breakdown (top 10)
$stmt = $pdo->query("
    SELECT filetype, COUNT(*) AS cnt, SUM(filesize) AS size
    FROM files
    WHERE filetype IS NOT NULL AND filetype != ''
    GROUP BY filetype
    ORDER BY cnt DESC
    LIMIT 10
");
$fileTypeBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Expiring soon (next 7 days)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM files
    WHERE expiry_date IS NOT NULL
    AND expiry_date > UTC_TIMESTAMP()
    AND expiry_date <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY)
");
$stmt->execute();
$expiringSoon = (int) $stmt->fetchColumn();
?>
<section class="stats-overview">
    <h3 class="page-title">Site Statistics</h3>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= number_format($totalUploads) ?></div>
            <div class="stat-label">Total Uploads</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= format_filesize($totalStorage) ?></div>
            <div class="stat-label">Storage Used</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($totalUsers) ?></div>
            <div class="stat-label">Registered Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($adminCount) ?></div>
            <div class="stat-label">Admins</div>
        </div>
    </div>

    <?php if ($expiringSoon > 0): ?>
    <div class="stats-alert">
        ⚠️ <strong><?= $expiringSoon ?></strong> file(s) will expire in the next 7 days.
        <a href="?section=files">View files</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($fileTypeBreakdown)): ?>
    <h4 class="stat-subtitle">File Types (Top 10)</h4>
    <div class="stat-table-wrap">
        <table class="stat-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Count</th>
                    <th>Total Size</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fileTypeBreakdown as $row): ?>
                <tr>
                    <td title="<?= sanitize_data($row['filetype']) ?>"><?= sanitize_data(get_friendly_filetype($row['filetype'])) ?></td>
                    <td><?= number_format($row['cnt']) ?></td>
                    <td><?= format_filesize((int) $row['size']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
