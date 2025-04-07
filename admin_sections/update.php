<?php
require_once __DIR__ . '/../includes/auth.php';
init_session();
require_admin();

require_once __DIR__ . '/../config/settings.php';

$githubRepo = 'ZacharyKeatings/DataDock';
$latestUrl = "https://api.github.com/repos/$githubRepo/releases/latest";
$zipPath = __DIR__ . '/tmp/latest-release.zip';
$extractPath = __DIR__ . '/tmp/update';
$log = [];

$dryRun = true;

function fetch_latest_release($url) {
    global $log;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'DataDock-Updater'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function download_file($url, $dest) {
    $fp = fopen($dest, 'w+');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_USERAGENT, 'DataDock-Updater');
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
}

function rrmdir($dir) {
    foreach (glob("$dir/*") as $file) {
        if (is_dir($file)) rrmdir($file);
        else unlink($file);
    }
    rmdir($dir);
}

// Start update
$release = fetch_latest_release($latestUrl);

if (!isset($release['zipball_url'])) {
    $_SESSION['flash_error'][] = '❌ Failed to fetch latest release info.';
    header("Location: ../admin.php?section=updater");
    exit;
}

$zipUrl = $release['zipball_url'];
$tag = $release['tag_name'] ?? 'unknown';



if ($dryRun) {
    $_SESSION['flash_success'][] = "🧪 <strong>Dry Run Mode Enabled</strong>";
    $_SESSION['flash_success'][] = "ℹ️ Would download release zip from: <code>$zipUrl</code>";
    $_SESSION['flash_success'][] = "📦 Would extract into: <code>$extractPath</code>";
    $_SESSION['flash_success'][] = "📁 Would skip files/folders: <code>config/settings.php, config/db.php, uploads/, thumbnails/</code>";
    $_SESSION['flash_success'][] = "🔁 Would overwrite other files with new version <strong>$tag</strong>";
    header("Location: ../admin.php?section=updater");
    exit;
}



download_file($zipUrl, $zipPath);

$zip = new ZipArchive;
if ($zip->open($zipPath) === TRUE) {
    $zip->extractTo($extractPath);
    $zip->close();

    $folders = glob("$extractPath/*");
    if (!empty($folders)) {
        $sourceFolder = $folders[0];

        $skip = ['config/settings.php', 'uploads', 'thumbnails', 'config/db.php'];

        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceFolder, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST) as $item
        ) {
            $destPath = str_replace($sourceFolder, __DIR__, $item);
            $relPath = str_replace(__DIR__ . '/', '', $destPath);

            if (in_array($relPath, $skip)) continue;

            if ($item->isDir()) {
                if (!file_exists($destPath)) mkdir($destPath);
            } else {
                copy($item, $destPath);
            }
        }

        rrmdir(dirname($sourceFolder));
        unlink($zipPath);

        $_SESSION['flash_success'][] = "✅ DataDock has been updated to <strong>$tag</strong>.";
    } else {
        $_SESSION['flash_error'][] = "❌ Failed to locate extracted release folder.";
    }
} else {
    $_SESSION['flash_error'][] = "❌ Failed to extract release zip.";
}

header("Location: ../admin.php?section=updater");
exit;
