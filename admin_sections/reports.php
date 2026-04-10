<?php
/** @var int $reportsTotal */
/** @var int $reportsTotalPages */
/** @var int $page */
/** @var int $perPage */
/** @var string $reportStatus */
/** @var array<int, array<string, mixed>> $reportsRows */
/** @var string|null $reportsError */
?>
<div class="admin-reports">
    <div class="file-mgmt-header">
        <h2 class="page-title">Reports &amp; moderation</h2>
        <p class="settings-hint" style="margin-top:0.5rem;max-width:52rem;">
            Review user reports for malicious or inappropriate files. Dismiss false positives or take file actions.
        </p>
    </div>

    <?php if ($reportsError !== null): ?>
        <p class="message message-error">Could not load reports: <?= sanitize_data($reportsError) ?></p>
    <?php else: ?>
        <div class="file-mgmt-toolbar" style="margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;">
            <span class="file-mgmt-stat"><strong><?= number_format($reportsTotal) ?></strong> report<?= $reportsTotal !== 1 ? 's' : '' ?></span>
            <div class="file-mgmt-filter">
                <span class="file-mgmt-filter-label">Status:</span>
                <a href="admin.php?section=reports&amp;status=open" class="btn btn-small<?= $reportStatus === 'open' ? ' btn-primary' : '' ?>">Open</a>
                <a href="admin.php?section=reports&amp;status=actioned" class="btn btn-small<?= $reportStatus === 'actioned' ? ' btn-primary' : '' ?>">Actioned</a>
                <a href="admin.php?section=reports&amp;status=dismissed" class="btn btn-small<?= $reportStatus === 'dismissed' ? ' btn-primary' : '' ?>">Dismissed</a>
            </div>
        </div>

        <?php if (!$reportsRows): ?>
            <p class="settings-hint">No reports in this status.</p>
        <?php else: ?>
            <div class="file-list file-list-admin" style="overflow-x:auto;">
                <div class="file-row-file-management file-header" style="grid-template-columns: 7rem 8rem 10rem 8rem 8rem 1fr 1fr;min-width:940px;">
                    <div>Time (UTC)</div>
                    <div>Reason</div>
                    <div>Reporter</div>
                    <div>File</div>
                    <div>Status</div>
                    <div>Details</div>
                    <div>Moderation</div>
                </div>
                <?php foreach ($reportsRows as $row): ?>
                    <?php
                    $reportId = (int) ($row['id'] ?? 0);
                    $fileId = (int) ($row['file_id'] ?? 0);
                    $fileName = (string) ($row['original_name'] ?? $row['filename'] ?? ('#' . $fileId));
                    $reason = (string) ($row['reason'] ?? 'other');
                    $reasonLabel = ucfirst(str_replace('_', ' ', $reason));
                    $details = (string) ($row['details'] ?? '');
                    $review = (string) ($row['review_note'] ?? '');
                    $detailsShort = strlen($details) > 120 ? substr($details, 0, 120) . '…' : $details;
                    $reviewShort = strlen($review) > 120 ? substr($review, 0, 120) . '…' : $review;
                    $isOpen = ($row['status'] ?? '') === 'open';
                    ?>
                    <div class="file-row-file-management" style="grid-template-columns: 7rem 8rem 10rem 8rem 8rem 1fr 1fr;min-width:940px;font-size:0.9rem;">
                        <div><span class="utc-datetime" data-utc="<?= sanitize_data($row['created_at'] ?? '') ?>"></span></div>
                        <div><?= sanitize_data($reasonLabel) ?></div>
                        <div><?= user_profile_link($row['reporter_username'] ?? null) ?></div>
                        <div class="file-name-cell" title="<?= sanitize_data($fileName) ?>">
                            <?php if ($fileId > 0): ?>
                                <a href="download.php?id=<?= $fileId ?>">#<?= $fileId ?></a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                        <div><?= sanitize_data((string) ($row['status'] ?? '')) ?></div>
                        <div class="file-name-cell" title="<?= sanitize_data($details) ?>"><?= $details !== '' ? sanitize_data($detailsShort) : '—' ?></div>
                        <div>
                            <?php if ($isOpen): ?>
                                <form method="post" action="admin.php?section=reports&amp;status=<?= sanitize_data($reportStatus) ?>" style="display:flex;gap:0.35rem;flex-wrap:wrap;align-items:flex-start;">
                                    <input type="hidden" name="report_id" value="<?= $reportId ?>">
                                    <textarea name="review_note" rows="2" maxlength="1000" placeholder="Review note (optional)" style="width:100%;margin-bottom:0.25rem;"></textarea>
                                    <button type="submit" name="moderation_report_action" value="dismiss" class="btn btn-small">Dismiss</button>
                                    <button type="submit" name="moderation_report_action" value="quarantine" class="btn btn-small">Quarantine</button>
                                    <button type="submit" name="moderation_report_action" value="delete" class="btn btn-small btn-danger" onclick="return confirm('Move this file to trash?');">Delete</button>
                                </form>
                            <?php else: ?>
                                <div class="settings-hint">
                                    By <?= sanitize_data((string) ($row['reviewer_username'] ?? 'unknown')) ?><br>
                                    <span class="utc-datetime" data-utc="<?= sanitize_data($row['reviewed_at'] ?? '') ?>"></span><br>
                                    <?= $review !== '' ? sanitize_data($reviewShort) : '—' ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($reportsTotalPages > 1): ?>
                <nav class="file-mgmt-filter" style="margin-top:1rem;gap:0.35rem;">
                    <?php for ($p = 1; $p <= $reportsTotalPages; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="btn btn-small btn-primary"><?= $p ?></span>
                        <?php else: ?>
                            <a href="admin.php?section=reports&amp;status=<?= sanitize_data($reportStatus) ?>&amp;p=<?= $p ?>" class="btn btn-small"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
