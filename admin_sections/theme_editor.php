<?php
require_once __DIR__ . '/../includes/themes.php';

$manifest = datadock_custom_theme_manifest();
$editSlug = isset($_GET['edit']) ? strtolower(trim((string) $_GET['edit'])) : '';
$editing = ($editSlug !== '' && isset($manifest[$editSlug]) && is_array($manifest[$editSlug])) ? $manifest[$editSlug] : null;
$tokens = is_array($editing['tokens'] ?? null) ? $editing['tokens'] : [];
$defaults = [
    'scheme' => 'light',
    'header' => '#038c96',
    'background' => '#f7f6f4',
    'text' => '#111111',
    'primary' => '#038c96',
    'secondary' => '#e7e4e0',
    'accent' => '#ffbc42',
    'border' => '#cccccc',
    'box' => '#ffffff',
    'link' => '#038c96',
    'link_hover' => '#026b73',
    'button_text' => '#ffffff',
    'font' => 'system',
    'radius' => '8px',
];
foreach ($defaults as $key => $value) {
    if (!isset($tokens[$key]) || !is_string($tokens[$key]) || $tokens[$key] === '') {
        $tokens[$key] = $value;
    }
}
$themeName = $editing ? (string) ($editing['name'] ?? $editSlug) : '';
$extraCss = $editing ? (string) ($editing['extra'] ?? '') : '';
$fontOptions = [
    'system' => 'System',
    'Georgia, "Iowan Old Style", "Palatino Linotype", serif' => 'Serif',
    'ui-monospace, "Cascadia Code", "Segoe UI Mono", monospace' => 'Monospace',
];
$colorFields = [
    'header' => 'Header',
    'background' => 'Page background',
    'text' => 'Text',
    'primary' => 'Primary',
    'secondary' => 'Panels',
    'accent' => 'Accent',
    'border' => 'Borders',
    'box' => 'Inputs',
    'link' => 'Links',
    'link_hover' => 'Link hover',
    'button_text' => 'Button text',
];
?>
<div class="admin-theme-editor">
    <h2 class="page-title">Themes</h2>
    <p class="page-description">Build a theme with the controls below. The sample updates as you change colors. Saving under a name adds it to the Default theme list in Site Settings and to each user’s profile.</p>

    <div class="theme-editor-layout">
        <form method="post" class="settings-card theme-editor-form" id="theme-editor-form">
            <h3 class="settings-card-title"><?= $editing ? 'Edit theme' : 'New theme' ?></h3>
            <div class="settings-card-body">
                <input type="hidden" name="save_custom_theme" value="1">
                <input type="hidden" name="theme_slug" value="<?= sanitize_data($editing ? $editSlug : '') ?>">
                <div class="settings-row">
                    <label for="theme_name">Theme name</label>
                    <input type="text" name="theme_name" id="theme_name" required maxlength="60" value="<?= sanitize_data($themeName) ?>" placeholder="Harbor">
                </div>
                <div class="settings-row">
                    <label for="theme_scheme">Color scheme</label>
                    <select name="theme_scheme" id="theme_scheme">
                        <option value="light"<?= $tokens['scheme'] === 'light' ? ' selected' : '' ?>>Light</option>
                        <option value="dark"<?= $tokens['scheme'] === 'dark' ? ' selected' : '' ?>>Dark</option>
                    </select>
                </div>
                <div class="theme-color-grid">
                    <?php foreach ($colorFields as $key => $label): ?>
                        <label class="theme-color-field">
                            <span><?= sanitize_data($label) ?></span>
                            <input type="color" name="token_<?= sanitize_data($key) ?>" value="<?= sanitize_data(datadock_hex_color((string) $tokens[$key], '#038c96')) ?>" data-token="<?= sanitize_data($key) ?>">
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="settings-row">
                    <label for="theme_font">Font</label>
                    <select name="theme_font" id="theme_font">
                        <?php foreach ($fontOptions as $value => $label): ?>
                            <option value="<?= sanitize_data($value) ?>"<?= $tokens['font'] === $value ? ' selected' : '' ?>><?= sanitize_data($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="settings-row">
                    <label for="theme_radius">Corner radius</label>
                    <select name="theme_radius" id="theme_radius">
                        <?php foreach (['2px' => 'Sharp', '8px' => 'Rounded', '14px' => 'Soft'] as $value => $label): ?>
                            <option value="<?= $value ?>"<?= $tokens['radius'] === $value ? ' selected' : '' ?>><?= sanitize_data($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="settings-row">
                    <label for="theme_extra">Extra CSS</label>
                    <textarea name="theme_extra" id="theme_extra" rows="8" placeholder="Optional rules appended after the generated theme"><?= sanitize_data($extraCss) ?></textarea>
                </div>
                <p class="settings-hint">Extra CSS is saved with the theme. Script tags, <code>@import</code>, and <code>javascript:</code> URLs are removed.</p>
                <button type="submit" class="btn btn-primary">Save theme</button>
            </div>
        </form>

        <div class="theme-stage-wrap">
            <h3 class="section-title">Preview</h3>
            <div class="theme-stage" id="theme-stage">
                <div class="theme-stage-header">DataDock</div>
                <div class="theme-stage-body">
                    <p>A <a href="#theme-stage">link</a> in the page.</p>
                    <div class="theme-sample-card">Panel</div>
                    <button type="button" class="btn btn-primary">Primary button</button>
                </div>
            </div>
        </div>
    </div>

    <div class="settings-card stack-gap">
        <h3 class="settings-card-title">Saved custom themes</h3>
        <div class="settings-card-body">
            <?php if (!$manifest): ?>
                <p class="settings-hint">No custom themes yet. Light, Dark, Midnight, Ocean, Forest, Sunset, High contrast, and Paper are bundled.</p>
            <?php else: ?>
                <ul class="theme-saved-list">
                    <?php foreach ($manifest as $slug => $row): ?>
                        <?php if (!is_string($slug) || !is_array($row)) { continue; } ?>
                        <li>
                            <span><?= sanitize_data((string) ($row['name'] ?? $slug)) ?></span>
                            <span class="theme-saved-actions">
                                <a class="btn btn-quiet btn-small" href="admin.php?section=themes&amp;edit=<?= urlencode($slug) ?>">Edit</a>
                                <form method="post" class="inline-form compact" onsubmit="return confirm('Delete this theme?');">
                                    <input type="hidden" name="delete_custom_theme" value="1">
                                    <input type="hidden" name="theme_slug" value="<?= sanitize_data($slug) ?>">
                                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                </form>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var stage = document.getElementById('theme-stage');
    var form = document.getElementById('theme-editor-form');
    if (!stage || !form) return;
    var map = {
        header: '--color-header-background',
        background: '--color-background',
        text: '--color-text',
        primary: '--color-primary',
        secondary: '--color-secondary',
        accent: '--color-accent',
        border: '--color-border',
        box: '--color-box-background',
        link: '--color-link',
        button_text: '--preview-button-text'
    };
    function paint() {
        Object.keys(map).forEach(function (key) {
            var input = form.querySelector('[data-token="' + key + '"]');
            if (input) stage.style.setProperty(map[key], input.value);
        });
        var font = form.querySelector('#theme_font');
        var radius = form.querySelector('#theme_radius');
        if (font) {
            var family = font.value === 'system' ? 'system-ui, sans-serif' : font.value;
            stage.style.fontFamily = family;
        }
        if (radius) stage.style.setProperty('--radius', radius.value);
    }
    form.addEventListener('input', paint);
    form.addEventListener('change', paint);
    paint();
});
</script>
