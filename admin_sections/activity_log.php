<?php
/** @var int $activityTotal */
/** @var int $activityTotalPages */
/** @var int $page */
/** @var int $perPage */
/** @var array<int, array<string, mixed>> $activityRows */
/** @var string|null $activityError */
?>
<div class="admin-activity-log">
    <div class="file-mgmt-header">
        <h2 class="page-title">Activity log</h2>
        <p class="settings-hint" style="margin-top:0.5rem;max-width:52rem;">
            Uploads, downloads, sharing, deletes, and other actions recorded for operations and auditing. Configure storage and quota alerts under <a href="admin.php?section=site">Site Settings</a> → Operational alerts.
        </p>
    </div>

    <?php if ($activityError !== null): ?>
        <p class="message message-error">Could not load activity log: <?= sanitize_data($activityError) ?></p>
    <?php else: ?>
        <div class="file-mgmt-toolbar" style="margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;">
            <span class="file-mgmt-stat"><strong><?= number_format($activityTotal) ?></strong> entr<?= $activityTotal !== 1 ? 'ies' : 'y' ?></span>
            <form method="post" class="file-mgmt-purge-form" style="display:flex;gap:0.35rem;align-items:center;" onsubmit="return confirm('Remove old activity log entries?');">
                <label for="activity_log_purge_days" class="settings-hint" style="margin:0;">Remove older than</label>
                <input type="number" name="activity_log_purge_days" id="activity_log_purge_days" value="90" min="1" max="3650" style="width:5rem;">
                <span class="settings-hint" style="margin:0;">days</span>
                <button type="submit" class="btn btn-small">Purge</button>
            </form>
        </div>

        <?php if (!$activityRows): ?>
            <p class="settings-hint">No activity recorded yet.</p>
        <?php else: ?>
            <div class="file-list file-list-admin" style="overflow-x:auto;">
                <div class="file-row-file-management file-header" style="grid-template-columns: 9rem 9rem 8rem 6rem 1fr 7rem;min-width:760px;">
                    <div>Time (UTC)</div>
                    <div>Action</div>
                    <div>Actor</div>
                    <div>File</div>
                    <div>Detail</div>
                    <div>IP</div>
                </div>
                <?php foreach ($activityRows as $row): ?>
                    <div class="file-row-file-management" style="grid-template-columns: 9rem 9rem 8rem 6rem 1fr 7rem;min-width:760px;font-size:0.9rem;">
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
                <nav class="file-mgmt-filter" style="margin-top:1rem;gap:0.35rem;">
                    <?php for ($p = 1; $p <= $activityTotalPages; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="btn btn-small btn-primary"><?= $p ?></span>
                        <?php else: ?>
                            <a href="admin.php?section=audit&amp;p=<?= $p ?>" class="btn btn-small"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
