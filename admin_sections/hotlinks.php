<?php
/** @var int $hotlinkTotal */
/** @var int $hotlinkTotalPages */
/** @var int $page */
/** @var int $perPage */
/** @var array<int, array<string, mixed>> $hotlinkRows */
/** @var string|null $hotlinkError */
?>
<div class="admin-hotlinks">
    <div class="file-mgmt-header">
        <h2 class="page-title">Hotlink log</h2>
        <p class="settings-hint" style="margin-top:0.5rem;max-width:52rem;">
            Entries appear when something is requested with a <code>Referer</code> from another host (e.g. another site embedding or linking your file, thumbnail, or avatar URL).
            Same-site navigation and direct opens with no referer are not logged. Configure logging and extra trusted hosts under <a href="admin.php?section=site">Site Settings</a>.
        </p>
    </div>

    <?php if ($hotlinkError !== null): ?>
        <p class="message message-error">Could not load hotlink log: <?= sanitize_data($hotlinkError) ?></p>
    <?php else: ?>
        <div class="file-mgmt-toolbar" style="margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;">
            <span class="file-mgmt-stat"><strong><?= number_format($hotlinkTotal) ?></strong> event<?= $hotlinkTotal !== 1 ? 's' : '' ?></span>
            <form method="post" onsubmit="return confirm('Delete all hotlink log entries?');">
                <input type="hidden" name="hotlink_purge_all" value="1">
                <button type="submit" class="btn btn-small">Clear log</button>
            </form>
            <form method="post" class="file-mgmt-purge-form" style="display:flex;gap:0.35rem;align-items:center;">
                <input type="hidden" name="hotlink_purge_old" value="1">
                <label for="purge_days" class="settings-hint" style="margin:0;">Remove older than</label>
                <input type="number" name="purge_days" id="purge_days" value="90" min="1" max="3650" style="width:5rem;">
                <span class="settings-hint" style="margin:0;">days</span>
                <button type="submit" class="btn btn-small">Purge</button>
            </form>
        </div>

        <?php if (!$hotlinkRows): ?>
            <p class="settings-hint">No off-site referer events recorded yet.</p>
        <?php else: ?>
            <div class="file-list file-list-admin" style="overflow-x:auto;">
                <div class="file-row-file-management file-header" style="grid-template-columns: 9rem 7rem 5rem 10rem 1fr 8rem;min-width:720px;">
                    <div>Time (UTC)</div>
                    <div>Resource</div>
                    <div>File</div>
                    <div>Referer host</div>
                    <div>Referer</div>
                    <div>IP</div>
                </div>
                <?php foreach ($hotlinkRows as $row): ?>
                    <div class="file-row-file-management" style="grid-template-columns: 9rem 7rem 5rem 10rem 1fr 8rem;min-width:720px;font-size:0.9rem;">
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
                <nav class="file-mgmt-filter" style="margin-top:1rem;gap:0.35rem;">
                    <?php for ($p = 1; $p <= $hotlinkTotalPages; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="btn btn-small btn-primary"><?= $p ?></span>
                        <?php else: ?>
                            <a href="admin.php?section=hotlinks&amp;p=<?= $p ?>" class="btn btn-small"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
