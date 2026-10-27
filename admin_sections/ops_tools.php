<?php
$diskResult = $_SESSION['ops_disk_scan_result'] ?? null;
$verifyResult = $_SESSION['ops_verify_result'] ?? null;
$rehashResult = $_SESSION['ops_rehash_result'] ?? null;
unset($_SESSION['ops_disk_scan_result'], $_SESSION['ops_verify_result'], $_SESSION['ops_rehash_result']);

$scriptPath = dirname(__DIR__) . '/scripts/datadock-cron-purge.php';
$cronExample = '0 * * * * php ' . $scriptPath . '  # hourly purge (adjust path)';
?>
<div class="admin-ops-tools">
    <h2 class="page-title">Backup &amp; integrity</h2>
    <p class="page-description">
        Export the database for disaster recovery, compare the uploads directory to the database, and verify or refresh file checksums after storage issues.
    </p>

    <div class="settings-card">
        <h4 class="settings-card-title">Backup / export</h4>
        <div class="settings-card-body">
            <p class="settings-hint">Downloads are generated on demand. For large databases, run <code>mysqldump</code> on the server if PHP time limits apply.</p>
            <div class="form-actions">
                <a href="backup_download.php?format=sql" class="btn btn-primary">Download SQL dump</a>
                <a href="backup_download.php?format=files_meta" class="btn btn-quiet">Download files metadata (JSON)</a>
            </div>
        </div>
    </div>

    <div class="settings-card">
        <h4 class="settings-card-title">Background purge (cron)</h4>
        <div class="settings-card-body">
            <p class="settings-hint">Purges expired files and trash past retention (same rules as the admin “Purge” actions). No daemon required.</p>
            <pre class="code-block"><?= sanitize_data($cronExample) ?></pre>
            <p class="settings-hint">Flags: <code>--no-trash</code> (expired only), <code>--no-expired</code> (trash only).</p>
        </div>
    </div>

    <div class="settings-card">
        <h4 class="settings-card-title">Disk vs database</h4>
        <div class="settings-card-body">
            <p class="settings-hint">Finds DB rows whose file is missing on disk, and upload files on disk that are not referenced (orphans).</p>
            <form method="post" class="form-actions">
                <button type="submit" name="ops_run_disk_scan" value="1" class="btn btn-primary">Run scan</button>
            </form>

            <?php if (is_array($diskResult)): ?>
                <?php
                $missing = $diskResult['missing'] ?? [];
                $orphaned = $diskResult['orphaned'] ?? [];
                ?>
                <p><strong>Missing on disk:</strong> <?= count($missing) ?></p>
                <?php if ($missing): ?>
                    <ul class="settings-hint scroll-list">
                        <?php foreach (array_slice($missing, 0, 100) as $m): ?>
                            <li>File #<?= (int) ($m['file_id'] ?? 0) ?> (partition <?= (int) ($m['partition_id'] ?? 0) ?>): <?= sanitize_data($m['name'] ?? '') ?></li>
                        <?php endforeach; ?>
                        <?php if (count($missing) > 100): ?>
                            <li>… and <?= count($missing) - 100 ?> more</li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
                <p><strong>Orphan files on disk:</strong> <?= count($orphaned) ?></p>
                <?php if ($orphaned): ?>
                    <ul class="settings-hint scroll-list">
                        <?php foreach (array_slice($orphaned, 0, 100) as $o): ?>
                            <li>Partition <?= (int) ($o['partition_id'] ?? 0) ?>: <?= sanitize_data($o['name'] ?? '') ?></li>
                        <?php endforeach; ?>
                        <?php if (count($orphaned) > 100): ?>
                            <li>… and <?= count($orphaned) - 100 ?> more</li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="settings-card">
        <h4 class="settings-card-title">Checksum verification</h4>
        <div class="settings-card-body">
            <p class="settings-hint">Compares stored MD5/SHA256 to bytes on disk. Leave limit at 0 to check all active files.</p>
            <form method="post" class="inline-form compact">
                <label for="verify_limit">Max rows (0 = all)</label>
                <input type="number" name="verify_limit" id="verify_limit" class="input-medium" value="0" min="0" max="50000">
                <button type="submit" name="ops_verify_checksums" value="1" class="btn btn-primary">Verify checksums</button>
            </form>
            <?php if (is_array($verifyResult)): ?>
                <p>Checked: <?= (int) ($verifyResult['checked'] ?? 0) ?> · Matching: <?= (int) ($verifyResult['ok'] ?? 0) ?> · Mismatch: <?= count($verifyResult['mismatch'] ?? []) ?></p>
                <?php foreach ($verifyResult['errors'] ?? [] as $err): ?>
                    <p class="flash error" role="alert"><?= sanitize_data($err) ?></p>
                <?php endforeach; ?>
                <?php if (!empty($verifyResult['mismatch'])): ?>
                    <ul class="settings-hint scroll-list scroll-list-tall">
                        <?php foreach ($verifyResult['mismatch'] as $row): ?>
                            <li>#<?= (int) ($row['file_id'] ?? 0) ?> <?= sanitize_data($row['name'] ?? '') ?> — MD5 expected <?= sanitize_data($row['expected_md5'] ?? '—') ?> actual <?= sanitize_data($row['actual_md5'] ?? '—') ?>; SHA256 expected <?= sanitize_data(substr((string) ($row['expected_sha256'] ?? ''), 0, 16)) ?>…</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="settings-card">
        <h4 class="settings-card-title">Re-hash from disk</h4>
        <div class="settings-card-body">
            <p class="settings-hint">Recomputes MD5 and SHA256 from current file bytes and updates the database (use after restoring files or repairing storage). Limit 0 = all active files.</p>
            <form method="post" class="inline-form compact" onsubmit="return confirm('Update checksums in the database from disk?');">
                <label for="rehash_limit">Max rows (0 = all)</label>
                <input type="number" name="rehash_limit" id="rehash_limit" class="input-medium" value="0" min="0" max="50000">
                <button type="submit" name="ops_rehash_from_disk" value="1" class="btn btn-danger">Re-hash and update DB</button>
            </form>
            <?php if (is_array($rehashResult)): ?>
                <p>Updated: <?= (int) ($rehashResult['updated'] ?? 0) ?> · Skipped: <?= (int) ($rehashResult['skipped'] ?? 0) ?></p>
                <?php foreach ($rehashResult['errors'] ?? [] as $err): ?>
                    <p class="flash warning" role="alert"><?= sanitize_data($err) ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
