<?php
$currentVersion = @file_get_contents(__DIR__ . '/../VERSION');
$githubRepo = 'ZacharyKeatings/DataDock';
$apiUrl = "https://api.github.com/repos/$githubRepo/releases/latest";
$changelogUrl = "https://raw.githubusercontent.com/$githubRepo/main/CHANGELOG.md";
$markdownBaseUrl = "https://github.com/$githubRepo/blob/main/";

$latestTag = null;
$releaseNotes = 'Unable to fetch release notes.';
$changelog = null;

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT => 'DataDock-Updater'
]);
$response = curl_exec($ch);
curl_close($ch);

if ($response) {
    $release = json_decode($response, true);
    $latestTag = $release['tag_name'] ?? 'unknown';
    $normalizedTag = ltrim($latestTag, 'v');

    $releaseNotes = $release['body'] ?? 'No release notes provided.';
} else {
    $normalizedTag = null;
}

$currentVersion = trim($currentVersion ?: '0');
$isLatest = $normalizedTag && (
    $currentVersion === $normalizedTag ||
    version_compare($currentVersion, $normalizedTag, '>=')
);

$ch = curl_init($changelogUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT => 'DataDock-Updater'
]);
$changelog = curl_exec($ch);
curl_close($ch);
?>

<h2 class="page-title">Update Checker</h2>

<p><strong>Current Version:</strong> v<?= sanitize_data($currentVersion) ?></p>
<p><strong>Latest Version:</strong> <?= sanitize_data($latestTag ?? 'N/A') ?></p>

<?php if (!$isLatest): ?>
    <?php
    $container_id   = 'updaterProgressContainer';
    $indeterminate  = true;
    $label          = 'Updating…';
    $hidden         = true;
    include __DIR__ . '/../includes/progress_bar.php';
    ?>
    <form id="updaterForm" method="post" action="admin_sections/update.php" onsubmit="return handleUpdaterSubmit(event, '<?= addslashes($latestTag ?? '') ?>');">
        <button type="submit" id="updaterBtn" class="btn btn-primary">Update to <?= $latestTag ?></button>
    </form>
    <script>
    function handleUpdaterSubmit(evt, tag) {
        if (!confirm('Update to ' + tag + '?')) return false;
        var container = document.getElementById('updaterProgressContainer');
        var btn = document.getElementById('updaterBtn');
        if (container) container.style.display = 'block';
        if (btn) { btn.disabled = true; btn.textContent = 'Updating…'; }
        return true;
    }
    </script>
<?php else: ?>
    <div class="success"><?= icon_svg('check') ?> You are running the latest version.</div>
<?php endif; ?>

<h3>Latest Release Notes</h3>
<div class="release-notes"><?= basic_markdown($releaseNotes, $markdownBaseUrl) ?></div>

<?php if ($changelog): 
    // Extract only the most recent changelog section (first ## version block until next ## or ---)
    if (preg_match('/##\s*\[[^\]]+\][^\n]*\n.*?(?=\n---|\n##\s*\[|$)/s', $changelog, $m)) {
        $changelogSection = trim($m[0]);
    } else {
        $changelogSection = $changelog;
    }
?>
    <h3>Recent Changelog</h3>
    <div class="release-notes"><?= basic_markdown($changelogSection, $markdownBaseUrl) ?></div>
<?php endif; ?>
