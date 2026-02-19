<?php
/**
 * Reusable progress bar component.
 *
 * Set any of these before including:
 *   container_id   - ID for the wrapper (for show/hide from JS).
 *   progress_id    - ID for the <progress> element.
 *   text_id        - ID for the label/percentage span.
 *   indeterminate  - If true, shows animated indeterminate state.
 *   value          - Current value (determinate; default 0).
 *   max            - Maximum value (default 100).
 *   label          - Initial text (e.g. "0%" or "Updating…").
 *   hidden         - If true, wrapper gets style="display:none;".
 */
$cid   = $container_id ?? '';
$pid   = $progress_id ?? '';
$tid   = $text_id ?? '';
$indet = $indeterminate ?? false;
$val   = isset($value) ? (int) $value : 0;
$max   = isset($max) ? (int) $max : 100;
$lbl   = $label ?? '0%';
$hide  = !empty($hidden);
?>
<div<?= $cid !== '' ? ' id="' . htmlspecialchars($cid) . '"' : '' ?> class="progress-bar-block"<?= $hide ? ' style="display:none;"' : '' ?>>
    <progress<?= $pid !== '' ? ' id="' . htmlspecialchars($pid) . '"' : '' ?> max="<?= $max ?>"<?= $indet ? '' : ' value="' . $val . '"' ?> aria-label="Progress"></progress>
    <?php if ($tid !== '' || $lbl !== ''): ?>
    <span<?= $tid !== '' ? ' id="' . htmlspecialchars($tid) . '"' : '' ?> class="progress-bar-text"><?= htmlspecialchars($lbl) ?></span>
    <?php endif; ?>
</div>
