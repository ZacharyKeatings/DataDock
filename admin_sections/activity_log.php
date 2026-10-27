<?php
/** @var int $activityTotal */
/** @var int $activityTotalPages */
/** @var int $page */
/** @var int $perPage */
/** @var array<int, array<string, mixed>> $activityRows */
/** @var string|null $activityError */
?>
<div class="admin-activity-log">
    <h2 class="page-title">Activity log</h2>
    <p class="page-description">
        Uploads, downloads, sharing, deletes, and other actions recorded for operations and auditing.
        Configure storage and quota alerts under <a href="admin.php?section=site">Site Settings</a>.
    </p>

    <?php if ($activityError !== null): ?>
        <div class="flash error" role="alert">Could not load activity log: <?= sanitize_data($activityError) ?></div>
    <?php else: ?>
        <div class="file-mgmt-toolbar">
            <span class="file-mgmt-stat"><strong><?= number_format($activityTotal) ?></strong> entr<?= $activityTotal !== 1 ? 'ies' : 'y' ?></span>
            <form method="post" class="inline-form compact" onsubmit="return confirm('Remove old activity log entries?');">
                <label for="activity_log_purge_days">Remove older than</label>
                <input type="number" name="activity_log_purge_days" id="activity_log_purge_days" class="input-compact" value="90" min="1" max="3650">
                <span>days</span>
                <button type="submit" class="btn btn-small">Purge</button>
            </form>
        </div>

        <?php if (!$activityRows): ?>
            <div class="empty-state"><p>No activity recorded yet.</p></div>
        <?php else: ?>
            <div class="file-list file-list-admin log-scroll">
                <div class="file-row-file-management file-header file-row-activity">
                    <div>Time (UTC)</div>
                    <div>Action</div>
                    <div>Actor</div>
                    <div>File</div>
                    <div>Detail</div>
                    <div>IP</div>
                </div>
                <?php foreach ($activityRows as $row): ?>
                    <div class="file-row-file-management file-row-activity">
                        <div><span class="utc-datetime" data-utc="<?= sanitize_data($row['created_at'] ?? '') ?>"></span></div>
                        <div><?= sanitize_data($row['action'] ?? '') ?></div>
                        <div>
                            <?php if (!empty($row['actor_username'])): ?>
                                <?= sanitize_data($row['actor_username']) ?>
                            <?php elseif (!empty($row['actor_guest_id'])): ?>
                                <span title="<?= sanitize_data($row['actor_guest_id']) ?>">guest…<?= sanitize_data(substr((string) $row['actor_guest_id'], -6)) ?></span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php $fid = $row['file_id'] ?? null; ?>
                            <?php if ($fid !== null && $fid !== ''): ?>
                                <a href="download.php?id=<?= (int) $fid ?>">#<?= (int) $fid ?></a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                        <?php
                        $det = (string) ($row['detail_json'] ?? '');
                        $detShort = strlen($det) > 160 ? substr($det, 0, 160) . '…' : $det;
                        ?>
                        <div class="file-name-cell" title="<?= sanitize_data($det) ?>"><?= sanitize_data($detShort) ?></div>
                        <div><?= sanitize_data($row['ip_address'] ?? '') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($activityTotalPages > 1): ?>
                <nav class="file-mgmt-filter pager">
                    <?php for ($p = 1; $p <= $activityTotalPages; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="btn btn-small btn-primary"><?= $p ?></span>
                        <?php else: ?>
                            <a href="admin.php?section=audit&amp;p=<?= $p ?>" class="btn btn-quiet btn-small"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
