<?php
require_once __DIR__ . '/includes/auth.php';
init_session();
require_login();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/user_trust.php';
require_once __DIR__ . '/includes/pagination.php';

$userId = (int) $_SESSION['user_id'];

datadock_maybe_record_storage_snapshot($pdo, $userId);

$bytesNow = datadock_user_total_storage_bytes($pdo, $userId);

$stmt = $pdo->prepare("
    SELECT DATE(recorded_at) AS d, MAX(bytes_total) AS b
    FROM user_storage_snapshots
    WHERE user_id = ? AND recorded_at > UTC_TIMESTAMP() - INTERVAL 180 DAY
    GROUP BY d
    ORDER BY d ASC
");
$stmt->execute([$userId]);
$chartRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$chartLabels = [];
$chartValues = [];
foreach ($chartRows as $r) {
    $chartLabels[] = (string) ($r['d'] ?? '');
    $chartValues[] = (int) ($r['b'] ?? 0);
}

$downloadCount = $pdo->prepare('SELECT COUNT(*) FROM file_download_events e INNER JOIN files f ON f.id = e.file_id AND f.user_id = ?');
$downloadCount->execute([$userId]);
$downloadPager = datadock_page_clamp(datadock_page_request('dp', 25), (int) $downloadCount->fetchColumn());
$stmt = $pdo->prepare("
    SELECT e.downloaded_at, e.ip_address, e.country_code, f.id AS file_id, f.original_name
    FROM file_download_events e
    INNER JOIN files f ON f.id = e.file_id AND f.user_id = ?
    ORDER BY e.downloaded_at DESC
    LIMIT {$downloadPager['per_page']} OFFSET {$downloadPager['offset']}
");
$stmt->execute([$userId]);
$recentDownloads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fileCountStmt = $pdo->prepare('SELECT COUNT(*) FROM files WHERE user_id = ? AND deleted_at IS NULL');
$fileCountStmt->execute([$userId]);
$uploadPager = datadock_page_clamp(datadock_page_request('fp', 25), (int) $fileCountStmt->fetchColumn());
$stmt = $pdo->prepare("
    SELECT id, original_name, download_count, upload_date
    FROM files
    WHERE user_id = ? AND deleted_at IS NULL
    ORDER BY upload_date DESC
    LIMIT {$uploadPager['per_page']} OFFSET {$uploadPager['offset']}
");
$stmt->execute([$userId]);
$myFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Activity & storage';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-section">
    <h2 class="page-title">Your activity</h2>
    <p class="page-description">Download history shows recent accesses to <strong>your</strong> files (approximate country when your host sends a country header, e.g. Cloudflare). Storage chart uses snapshots recorded when you use the dashboard (at most once per hour).</p>

    <p><strong>Storage in use:</strong> <?= format_filesize($bytesNow) ?></p>

    <?php if (count($chartLabels) > 0): ?>
    <div class="storage-chart">
        <canvas id="storageChart" height="120" aria-label="Storage over time"></canvas>
    </div>
    <?php else: ?>
    <p class="form-hint">No storage history yet. Opening your files records a snapshot at most once an hour.</p>
    <?php endif; ?>

    <h3 class="section-title">Recent downloads of your files</h3>
    <?php if (empty($recentDownloads)): ?>
        <div class="empty-state"><p>No downloads logged yet.</p></div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">When (UTC)</th>
                        <th scope="col">File</th>
                        <th scope="col">IP</th>
                        <th scope="col">Country</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentDownloads as $ev): ?>
                    <tr>
                        <td><span class="utc-datetime" data-utc="<?= sanitize_data($ev['downloaded_at']) ?>"></span></td>
                        <td><?= sanitize_data($ev['original_name'] ?? '') ?> <span class="form-hint">(#<?= (int) $ev['file_id'] ?>)</span></td>
                        <td><code><?= sanitize_data($ev['ip_address'] ?? '') ?></code></td>
                        <td><?= !empty($ev['country_code']) ? sanitize_data($ev['country_code']) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php datadock_render_pager($downloadPager); ?>
    <?php endif; ?>

    <h3 class="section-title">Your uploads</h3>
    <?php if (empty($myFiles)): ?>
        <div class="empty-state"><p>No files yet.</p></div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Downloads (total)</th>
                        <th scope="col">Uploaded</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myFiles as $f): ?>
                    <tr>
                        <td><?= sanitize_data($f['original_name'] ?? '') ?></td>
                        <td><?= (int) ($f['download_count'] ?? 0) ?></td>
                        <td><span class="utc-datetime" data-utc="<?= sanitize_data($f['upload_date']) ?>"></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php datadock_render_pager($uploadPager); ?>
    <?php endif; ?>

    <p class="form-actions"><a href="export_data.php" class="btn btn-quiet">Export my data</a></p>
</div>

<?php if (count($chartLabels) > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
    const values = <?= json_encode($chartValues, JSON_UNESCAPED_UNICODE) ?>;
    const ctx = document.getElementById('storageChart');
    if (!ctx || typeof Chart === 'undefined') return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Storage (bytes)',
                data: values,
                fill: true,
                tension: 0.2
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.utc-datetime').forEach(el => {
        const utc = el.dataset.utc;
        if (utc) {
            el.textContent = new Date(utc.replace(' ', 'T') + 'Z').toLocaleString();
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
