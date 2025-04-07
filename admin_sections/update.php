<?php
ob_start();

require_once __DIR__ . '/../includes/auth.php';
init_session();
require_admin();

require_once __DIR__ . '/../config/settings.php';

$githubRepo   = 'ZacharyKeatings/DataDock';
$latestUrl    = "https://api.github.com/repos/$githubRepo/releases/latest";
$zipPath      = __DIR__ . '/../tmp/latest-release.zip';
$extractPath  = __DIR__ . '/../tmp/update';
$dryRun       = false;

/**
 * Fetches the latest release from GitHub and returns the release data,
 * including a download URL for the .zip or .tar.gz file.
 */
function fetch_latest_release($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'DataDock-Updater'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $release = json_decode($response, true);

    if (!$release || !isset($release['tag_name'])) return null;

    // Prefer .zip asset if available
    foreach ($release['assets'] ?? [] as $asset) {
        if (str_ends_with($asset['name'], '.zip')) {
            $release['download_url'] = $asset['browser_download_url'];
            return $release;
        }
    }

    // Fallback to .tar.gz
    foreach ($release['assets'] ?? [] as $asset) {
        if (str_ends_with($asset['name'], '.tar.gz')) {
            $release['download_url'] = $asset['browser_download_url'];
            return $release;
        }
    }

    $release['download_url'] = null;
    return $release;
}

/**
 * Downloads a remote file from GitHub to the specified path.
 */
function download_file($url, $dest) {
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $fp = fopen($dest, 'w+');
    if (!$fp) {
        throw new RuntimeException("❌ Failed to open file for writing: $dest");
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'DataDock-Updater',
        CURLOPT_HTTPHEADER     => ['Accept: application/octet-stream'],
        CURLOPT_FAILONERROR    => true
    ]);

    $success = curl_exec($ch);

    if (!$success) {
        $error = curl_error($ch);
        fclose($fp);
        unlink($dest);
        curl_close($ch);
        throw new RuntimeException("❌ cURL download failed: $error");
    }

    curl_close($ch);
    fclose($fp);
}

/**
 * Recursively deletes a directory.
 */
function rrmdir($dir) {
    foreach (glob("$dir/*") as $file) {
        is_dir($file) ? rrmdir($file) : unlink($file);
    }
    rmdir($dir);
}

/**
 * Searches extracted content for the root project folder by locating index.php.
 */
function find_project_root($path) {
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($rii as $file) {
        if (basename($file) === 'index.php') {
            return dirname($file);
        }
    }

    return null;
}

// === Begin Update Process ===

$release = fetch_latest_release($latestUrl);

if (!isset($release['download_url'])) {
    $_SESSION['flash_error'][] = '❌ Failed to fetch latest release info.';
    ob_end_clean();
    header("Location: ../admin.php?section=updater");
    exit;
}

$zipUrl = $release['download_url'];
$tag    = $release['tag_name'] ?? 'unknown';

if ($dryRun) {
    $_SESSION['flash_success'][] = "🧪 <strong>Dry Run Mode Enabled</strong>";
    $_SESSION['flash_success'][] = "ℹ️ Would download release zip from: <code>$zipUrl</code>";
    $_SESSION['flash_success'][] = "📦 Would extract into: <code>$extractPath</code>";
    $_SESSION['flash_success'][] = "📁 Would skip files/folders: <code>config/settings.php, config/db.php, uploads/, thumbnails/</code>";
    $_SESSION['flash_success'][] = "🔁 Would overwrite other files with new version <strong>$tag</strong>";
    ob_end_clean();
    header("Location: ../admin.php?section=updater");
    exit;
}

// Download
try {
    download_file($zipUrl, $zipPath);
} catch (Exception $e) {
    $_SESSION['flash_error'][] = "❌ Download failed: " . $e->getMessage();
    ob_end_clean();
    header("Location: ../admin.php?section=updater");
    exit;
}

// Verify
if (!file_exists($zipPath) || filesize($zipPath) === 0) {
    $_SESSION['flash_error'][] = "❌ Downloaded ZIP is empty or missing.";
    ob_end_clean();
    header("Location: ../admin.php?section=updater");
    exit;
}

// Extract
$zip = new ZipArchive;
if ($zip->open($zipPath) === TRUE) {
    $zip->extractTo($extractPath);
    $zip->close();
} else {
    $_SESSION['flash_error'][] = "❌ Failed to extract release zip.";
    ob_end_clean();
    header("Location: ../admin.php?section=updater");
    exit;
}

// Find root folder
$sourceFolder = find_project_root($extractPath);
if (!$sourceFolder) {
    $_SESSION['flash_error'][] = "❌ Could not locate extracted project root (missing index.php).";
    ob_end_clean();
    header("Location: ../admin.php?section=updater");
    exit;
}

// Overwrite files
$skip = ['config/settings.php', 'config/db.php', 'uploads', 'thumbnails'];

foreach (new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceFolder, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
) as $item) {
    $destPath = str_replace($sourceFolder, __DIR__ . '/..', $item);
    $relPath  = str_replace(__DIR__ . '/../', '', $destPath);

    // Skip protected paths
    foreach ($skip as $skipped) {
        if (stripos($relPath, rtrim($skipped, '/')) === 0) {
            continue 2;
        }
    }

    if ($item->isDir()) {
        if (!file_exists($destPath)) mkdir($destPath);
    } else {
        copy($item, $destPath);
    }
}

// Clean up
rrmdir($extractPath);
unlink($zipPath);

$_SESSION['flash_success'][] = "✅ DataDock has been updated to <strong>$tag</strong>.";

ob_end_clean();
header("Location: ../admin.php?section=updater");
exit;
