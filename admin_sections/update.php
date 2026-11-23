<?php
ob_start();

require_once __DIR__ . '/../includes/auth.php';
init_session();
require_admin();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ../admin.php?section=updater');
    exit;
}
datadock_reject_invalid_csrf('../admin.php?section=updater');

require_once __DIR__ . '/../includes/settings_loader.php';
require_once __DIR__ . '/../includes/updater.php';
$settings = datadock_load_settings();

if (datadock_docker_image_mode()) {
    $_SESSION['flash_error'][] = 'This copy is a Docker image. Pull a new image instead of using the in-app updater.';
    header('Location: ../admin.php?section=updater');
    exit;
}

$zipPath      = __DIR__ . '/../tmp/latest-release.zip';
$extractPath  = __DIR__ . '/../tmp/update';
$dryRun       = false;

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

$release = datadock_fetch_update_target();
$zipUrl = (string) ($release['asset_url'] ?? '');
$tag = (string) ($release['tag'] ?? 'unknown');

if (empty($release['ok']) || $zipUrl === '' || !str_ends_with(parse_url($zipUrl, PHP_URL_PATH) ?: '', '.zip')) {
    $_SESSION['flash_error'][] = $release['error'] ?? '❌ No installable zip is attached to that release.';
    ob_end_clean();
    header("Location: ../admin.php?section=updater");
    exit;
}

if ($dryRun) {
    $tagEsc = htmlspecialchars($tag, ENT_QUOTES, 'UTF-8');
    $zipUrlEsc = htmlspecialchars($zipUrl, ENT_QUOTES, 'UTF-8');
    $extractPathEsc = htmlspecialchars($extractPath, ENT_QUOTES, 'UTF-8');
    $_SESSION['flash_success'][] = ['html' => true, 'msg' => "🧪 <strong>Dry Run Mode Enabled</strong>"];
    $_SESSION['flash_success'][] = ['html' => true, 'msg' => "ℹ️ Would download release zip from: <code>$zipUrlEsc</code>"];
    $_SESSION['flash_success'][] = ['html' => true, 'msg' => "📦 Would extract into: <code>$extractPathEsc</code>"];
    $_SESSION['flash_success'][] = ['html' => true, 'msg' => "📁 Would skip files/folders: <code>config/settings.php, config/db.php, uploads/, thumbnails/</code>"];
    $_SESSION['flash_success'][] = ['html' => true, 'msg' => "🔁 Would overwrite other files with new version <strong>$tagEsc</strong>"];
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
$skip = ['config/settings.php', 'config/db.php', 'config/update-channel.txt', 'uploads', 'thumbnails'];

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

$_SESSION['flash_success'][] = ['html' => true, 'msg' => "✅ DataDock has been updated to <strong>" . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . "</strong>."];

ob_end_clean();
header("Location: ../admin.php?section=updater");
exit;
