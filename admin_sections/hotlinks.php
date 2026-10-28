<?php
/** @var int $hotlinkTotal */
/** @var int $hotlinkTotalPages */
/** @var int $page */
/** @var int $perPage */
/** @var array<int, array<string, mixed>> $hotlinkRows */
/** @var string|null $hotlinkError */
?>
<div class="admin-hotlinks">
    <h2 class="page-title">Hotlink log</h2>
    <p class="page-description">
        Entries appear when something is requested with a Referer from another host, such as another site embedding or linking a file, thumbnail, or avatar URL.
        Same-site navigation and direct opens with no referer are not logged. Configure logging and extra trusted hosts under <a href="admin.php?section=site&amp;group=security">Security</a>.
    </p>

    <?php if ($hotlinkError !== null): ?>
        <div class="flash error" role="alert">Could not load hotlink log: <?= sanitize_data($hotlinkError) ?></div>
    <?php else: ?>
        <div class="file-mgmt-toolbar">
            <span class="file-mgmt-stat"><strong><?= number_format($hotlinkTotal) ?></strong> event<?= $hotlinkTotal !== 1 ? 's' : '' ?></span>
            <form method="post" class="inline-form compact" onsubmit="return confirm('Delete all hotlink log entries?');">
                <input type="hidden" name="hotlink_purge_all" value="1">
                <button type="submit" class="btn btn-danger btn-small">Clear log</button>
            </form>
            <form method="post" class="inline-form compact">
                <input type="hidden" name="hotlink_purge_old" value="1">
                <label for="purge_days">Remove older than</label>
                <input type="number" name="purge_days" id="purge_days" class="input-compact" value="90" min="1" max="3650">
                <span>days</span>
                <button type="submit" class="btn btn-small">Purge</button>
            </form>
        </div>

        <?php if (!$hotlinkRows): ?>
            <div class="empty-state"><p>No off-site referer events recorded yet.</p></div>
        <?php else: ?>
            <div class="file-list file-list-admin log-scroll">
                <div class="file-row-file-management file-header file-row-hotlinks">
                    <div>Time (UTC)</div>
                    <div>Resource</div>
                    <div>File</div>
                    <div>Referer host</div>
                    <div>Referer</div>
                    <div>IP</div>
                </div>
                <?php foreach ($hotlinkRows as $row): ?>
                    <div class="file-row-file-management file-row-hotlinks">
                        <div><span class="utc-datetime" data-utc="<?= sanitize_data($row['created_at'] ?? '') ?>"></span></div>
                        <div><?= sanitize_data($row['resource'] ?? '') ?></div>
                        <div>
                            <?php
                            $fid = $row['file_id'] ?? null;
                            if ($fid !== null && $fid !== ''): ?>
                                <a href="download.php?id=<?= (int) $fid ?>">#<?= (int) $fid ?></a>
                            <?php elseif (!empty($row['target_user_id'])): ?>
                                user <?= (int) $row['target_user_id'] ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                        <div title="<?= sanitize_data($row['referer_host'] ?? '') ?>"><?= sanitize_data($row['referer_host'] ?? '') ?></div>
                        <?php $refFull = (string) ($row['referer'] ?? ''); $refShort = strlen($refFull) > 120 ? substr($refFull, 0, 120) . '…' : $refFull; ?>
                        <div class="file-name-cell" title="<?= sanitize_data($refFull) ?>"><?= sanitize_data($refShort) ?></div>
                        <div><?= sanitize_data($row['ip_address'] ?? '') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($hotlinkTotalPages > 1): ?>
                <nav class="file-mgmt-filter pager">
                    <?php for ($p = 1; $p <= $hotlinkTotalPages; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="btn btn-small btn-primary"><?= $p ?></span>
                        <?php else: ?>
                            <a href="admin.php?section=hotlinks&amp;p=<?= $p ?>" class="btn btn-quiet btn-small"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
