<?php
/** @var int $reportsTotal */
/** @var int $reportsTotalPages */
/** @var int $page */
/** @var int $perPage */
/** @var string $reportStatus */
/** @var array<int, array<string, mixed>> $reportsRows */
/** @var string|null $reportsError */
/** @var int $reportIdFilter */
/** @var int $reportsFileIdFilter */

$reportIdFilter = $reportIdFilter ?? 0;
$reportsFileIdFilter = $reportsFileIdFilter ?? 0;

$reportsListQuery = static function (array $parts): string {
    return 'admin.php?' . http_build_query($parts);
};
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
        <?php if ($reportIdFilter > 0): ?>
            <p class="settings-hint admin-reports-filter-banner">
                Showing report <strong>#<?= (int) $reportIdFilter ?></strong>.
                <a href="<?= sanitize_data($reportsListQuery(['section' => 'reports', 'status' => $reportStatus])) ?>">View full list</a>
            </p>
        <?php elseif ($reportsFileIdFilter > 0): ?>
            <p class="settings-hint admin-reports-filter-banner">
                Filtered to file <strong>#<?= (int) $reportsFileIdFilter ?></strong>.
                <a href="<?= sanitize_data($reportsListQuery(['section' => 'reports', 'status' => $reportStatus])) ?>">Clear file filter</a>
                ·
                <a href="<?= sanitize_data($reportsListQuery(['section' => 'files'])) ?>">File management</a>
            </p>
        <?php endif; ?>

        <div class="file-mgmt-toolbar admin-reports-toolbar">
            <span class="file-mgmt-stat"><strong><?= number_format($reportsTotal) ?></strong> report<?= $reportsTotal !== 1 ? 's' : '' ?></span>
            <div class="file-mgmt-filter">
                <span class="file-mgmt-filter-label">Status:</span>
                <?php
                $statusQs = ['section' => 'reports'];
                if ($reportsFileIdFilter > 0) {
                    $statusQs['file_id'] = $reportsFileIdFilter;
                }
                ?>
                <a href="<?= sanitize_data($reportsListQuery(array_merge($statusQs, ['status' => 'open']))) ?>" class="btn btn-small<?= $reportStatus === 'open' ? ' btn-primary' : '' ?>">Open</a>
                <a href="<?= sanitize_data($reportsListQuery(array_merge($statusQs, ['status' => 'actioned']))) ?>" class="btn btn-small<?= $reportStatus === 'actioned' ? ' btn-primary' : '' ?>">Actioned</a>
                <a href="<?= sanitize_data($reportsListQuery(array_merge($statusQs, ['status' => 'dismissed']))) ?>" class="btn btn-small<?= $reportStatus === 'dismissed' ? ' btn-primary' : '' ?>">Dismissed</a>
            </div>
        </div>

        <?php if (!$reportsRows): ?>
            <p class="settings-hint"><?= $reportIdFilter > 0 ? 'Report not found.' : 'No reports in this status.' ?></p>
        <?php else: ?>
            <div class="file-list file-list-admin file-list-reports">
                <div class="file-row-file-management file-header file-row-reports">
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
                    <div class="file-row-file-management file-row-reports">
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
                                <?php
                                $modActionQs = ['section' => 'reports', 'status' => $reportStatus];
                                if ($reportsFileIdFilter > 0) {
                                    $modActionQs['file_id'] = $reportsFileIdFilter;
                                }
                                if ($reportIdFilter > 0) {
                                    $modActionQs['report_id'] = $reportIdFilter;
                                }
                                ?>
                                <form method="post" action="<?= sanitize_data($reportsListQuery($modActionQs)) ?>" class="admin-reports-moderation-form">
                                    <input type="hidden" name="report_id" value="<?= $reportId ?>">
                                    <textarea name="review_note" rows="2" maxlength="1000" placeholder="Review note (optional)" class="admin-reports-review-note"></textarea>
                                    <div class="admin-reports-actions">
                                        <button type="submit" name="moderation_report_action" value="dismiss" class="btn btn-small">Dismiss</button>
                                        <button type="submit" name="moderation_report_action" value="quarantine" class="btn btn-small">Quarantine</button>
                                        <button type="submit" name="moderation_report_action" value="delete" class="btn btn-small btn-danger" onclick="return confirm('Move this file to trash?');">Delete</button>
                                    </div>
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
                <nav class="file-mgmt-filter admin-reports-pagination">
                    <?php for ($p = 1; $p <= $reportsTotalPages; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="btn btn-small btn-primary"><?= $p ?></span>
                        <?php else: ?>
                            <?php
                            $pageQs = ['section' => 'reports', 'status' => $reportStatus, 'p' => $p];
                            if ($reportsFileIdFilter > 0) {
                                $pageQs['file_id'] = $reportsFileIdFilter;
                            }
                            ?>
                            <a href="<?= sanitize_data($reportsListQuery($pageQs)) ?>" class="btn btn-small"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
