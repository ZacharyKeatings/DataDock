<?php
require_once __DIR__ . '/../includes/updater.php';

$currentVersion = trim((string) (@file_get_contents(__DIR__ . '/../VERSION') ?: '0'));
$githubRepo = 'ZacharyKeatings/DataDock';
$changelogUrl = "https://raw.githubusercontent.com/$githubRepo/main/CHANGELOG.md";
$markdownBaseUrl = "https://github.com/$githubRepo/blob/main/";
$channel = datadock_update_channel();
$dockerImage = datadock_docker_image_mode();
$target = $dockerImage ? null : datadock_fetch_update_target($channel);
$relation = 'current';
$latestTag = null;
$releaseNotes = '';

if (is_array($target) && !empty($target['ok']) && !empty($target['release'])) {
    $latestTag = (string) ($target['tag'] ?? '');
    $releaseNotes = (string) ($target['notes'] ?? '');
    $relation = datadock_update_relation($currentVersion, (string) ($target['version'] ?? ''));
}

$ch = curl_init($changelogUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT => 'DataDock-Updater',
]);
$changelog = curl_exec($ch);
curl_close($ch);

$channelLabel = $channel === 'development' ? 'development' : 'stable';
$buttonLabel = $relation === 'downgrade'
    ? ($channel === 'stable' ? 'Switch back to ' : 'Install ') . $latestTag
    : 'Update to ' . $latestTag;
$confirmMessage = $relation === 'downgrade'
    ? 'Replace the application files with ' . $latestTag . '? Settings, uploads, and the database stay in place. A database change from a newer version is not undone.'
    : 'Update to ' . $latestTag . '?';
?>

<h2 class="page-title">Update Checker</h2>
<p class="page-description">Current version v<?= sanitize_data($currentVersion) ?>.</p>

<?php if ($dockerImage): ?>
    <div class="flash warning persistent" role="status">
        This copy is running in a Docker image, so the in-app updater is turned off.
        Update by pulling a new image and recreating the container, for example
        <code>docker compose pull &amp;&amp; docker compose up -d</code>.
        Use the <code>latest</code> tag for stable releases and the <code>dev</code> tag for the <code>dev-testing</code> branch.
    </div>
<?php else: ?>
    <form method="post" action="admin.php?section=updater">
        <?= datadock_csrf_field() ?>
        <input type="hidden" name="update_channel_save" value="1">
        <fieldset>
            <legend>Release channel</legend>
            <label>
                <input type="radio" name="update_channel" value="stable" <?= $channel === 'stable' ? 'checked' : '' ?>>
                Stable
            </label>
            <label>
                <input type="radio" name="update_channel" value="development" <?= $channel === 'development' ? 'checked' : '' ?>>
                Development
            </label>
            <p class="form-hint">Development installs the newest pre-release. Choosing Stable again installs the newest stable release, including when its version number is lower than the one installed now.</p>
            <button type="submit" class="btn">Save channel</button>
        </fieldset>
    </form>

    <?php if (is_array($target) && empty($target['ok'])): ?>
        <div class="flash error persistent" role="alert"><?= sanitize_data((string) ($target['error'] ?? 'Could not check for updates.')) ?></div>
    <?php elseif ($latestTag === null || $latestTag === ''): ?>
        <div class="flash warning persistent" role="status">No <?= sanitize_data($channelLabel) ?> release is published yet.</div>
    <?php elseif ($relation === 'current'): ?>
        <div class="flash success persistent" role="status"><?= icon_svg('check') ?> You are running the latest <?= sanitize_data($channelLabel) ?> version (<?= sanitize_data($latestTag) ?>).</div>
    <?php elseif (empty($target['asset_url']) || !str_ends_with((string) parse_url((string) $target['asset_url'], PHP_URL_PATH), '.zip')): ?>
        <div class="flash warning persistent" role="status"><?= sanitize_data($latestTag) ?> is published, but it has no zip package yet.</div>
    <?php else: ?>
        <?php
        $container_id   = 'updaterProgressContainer';
        $indeterminate  = true;
        $label          = 'Updating…';
        $hidden         = true;
        include __DIR__ . '/../includes/progress_bar.php';
        ?>
        <form id="updaterForm" method="post" action="admin_sections/update.php" onsubmit="return handleUpdaterSubmit(event);">
            <?= datadock_csrf_field() ?>
            <button type="submit" id="updaterBtn" class="btn btn-primary"><?= sanitize_data($buttonLabel) ?></button>
        </form>
        <script>
        function handleUpdaterSubmit(evt) {
            if (!confirm(<?= json_encode($confirmMessage) ?>)) return false;
            var container = document.getElementById('updaterProgressContainer');
            var btn = document.getElementById('updaterBtn');
            if (container) container.style.display = 'block';
            if (btn) { btn.disabled = true; btn.textContent = 'Updating…'; }
            return true;
        }
        </script>
    <?php endif; ?>

    <?php if ($releaseNotes !== ''): ?>
        <h3 class="section-title"><?= $channel === 'development' ? 'Development release notes' : 'Latest release notes' ?></h3>
        <div class="release-notes"><?= basic_markdown($releaseNotes, $markdownBaseUrl) ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($changelog):
    if (preg_match('/##\s*\[[^\]]+\][^\n]*\n.*?(?=\n---|\n##\s*\[|$)/s', $changelog, $m)) {
        $changelogSection = trim($m[0]);
    } else {
        $changelogSection = $changelog;
    }
?>
    <h3 class="section-title">Recent changelog</h3>
    <div class="release-notes"><?= basic_markdown($changelogSection, $markdownBaseUrl) ?></div>
<?php endif; ?>
