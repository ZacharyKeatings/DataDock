<?php
$currentVersion = @file_get_contents(__DIR__ . '/../VERSION');
$githubRepo = 'ZacharyKeatings/DataDock';
$apiUrl = "https://api.github.com/repos/$githubRepo/releases/latest";
$changelogUrl = "https://raw.githubusercontent.com/$githubRepo/main/CHANGELOG.md";

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

$isLatest = $normalizedTag === $currentVersion;

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
    <form method="post" action="admin_sections/update.php" onsubmit="return confirm('Update to <?= $latestTag ?>?');">
        <button type="submit" class="btn btn-primary">Update to <?= $latestTag ?></button>
    </form>
<?php else: ?>
    <div class="success">✅ You are running the latest version.</div>
<?php endif; ?>

<h3>Latest Release Notes</h3>
<div class="release-notes"><?= basic_markdown($releaseNotes) ?></div>

<?php if ($changelog): ?>
    <h3>Full Changelog</h3>
    <pre class="changelog-box"><?= basic_markdown($changelog) ?></pre>
<?php endif; ?>
