<?php

/**
 * In-app updater channel and GitHub release selection.
 * Stable tracks the newest full release. Development tracks the newest pre-release.
 */

function datadock_update_channel_file(?string $path = null): string {
    return $path ?? (dirname(__DIR__) . '/config/update-channel.txt');
}

function datadock_update_channel(?string $path = null): string {
    $file = datadock_update_channel_file($path);
    if (!is_file($file)) {
        return 'stable';
    }
    $value = strtolower(trim((string) file_get_contents($file)));
    return $value === 'development' ? 'development' : 'stable';
}

function datadock_set_update_channel(string $channel, ?string $path = null): bool {
    $channel = $channel === 'development' ? 'development' : 'stable';
    $file = datadock_update_channel_file($path);
    $dir = dirname($file);
    if (!is_dir($dir) || !is_writable($dir)) {
        return false;
    }
    return file_put_contents($file, $channel . "\n", LOCK_EX) !== false;
}

function datadock_release_version(string $tag): string {
    return ltrim(trim($tag), 'vV');
}

/**
 * @return 'current'|'upgrade'|'downgrade'
 */
function datadock_update_relation(string $current, string $target): string {
    $cmp = version_compare(datadock_release_version($current), datadock_release_version($target));
    if ($cmp === 0) {
        return 'current';
    }
    return $cmp < 0 ? 'upgrade' : 'downgrade';
}

/**
 * Newest matching release. GitHub returns releases newest first.
 *
 * @param array<int, mixed> $releases
 * @return array<string, mixed>|null
 */
function datadock_pick_release(array $releases, string $channel): ?array {
    $wantPre = $channel === 'development';
    foreach ($releases as $release) {
        if (!is_array($release) || empty($release['tag_name'])) {
            continue;
        }
        if (!empty($release['draft'])) {
            continue;
        }
        if ($wantPre === !empty($release['prerelease'])) {
            return $release;
        }
    }
    return null;
}

/**
 * @param array<string, mixed> $release
 */
function datadock_release_asset_url(array $release): ?string {
    $zip = null;
    $tar = null;
    foreach ($release['assets'] ?? [] as $asset) {
        if (!is_array($asset)) {
            continue;
        }
        $name = (string) ($asset['name'] ?? '');
        $url = (string) ($asset['browser_download_url'] ?? '');
        if ($url === '') {
            continue;
        }
        if ($zip === null && substr($name, -4) === '.zip') {
            $zip = $url;
        }
        if ($tar === null && substr($name, -7) === '.tar.gz') {
            $tar = $url;
        }
    }
    return $zip ?? $tar;
}

/**
 * @return array<string, mixed>|null
 */
function datadock_github_api_get(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'DataDock-Updater',
        CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($response) || $response === '' || $code >= 400) {
        return null;
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * @return array{ok: bool, channel: string, error?: string, release?: ?array, tag?: string, version?: string, asset_url?: ?string, notes?: string}
 */
function datadock_fetch_update_target(?string $channel = null): array {
    $channel = ($channel ?? datadock_update_channel()) === 'development' ? 'development' : 'stable';
    $url = $channel === 'development'
        ? 'https://api.github.com/repos/ZacharyKeatings/DataDock/releases?per_page=30'
        : 'https://api.github.com/repos/ZacharyKeatings/DataDock/releases/latest';
    $payload = datadock_github_api_get($url);
    if ($payload === null) {
        return [
            'ok' => false,
            'channel' => $channel,
            'error' => 'Could not reach GitHub to check for updates.',
        ];
    }

    $release = $channel === 'development'
        ? datadock_pick_release($payload, 'development')
        : (isset($payload['tag_name']) ? $payload : null);
    if (!is_array($release)) {
        return ['ok' => true, 'channel' => $channel, 'release' => null];
    }

    $tag = (string) ($release['tag_name'] ?? '');
    return [
        'ok' => true,
        'channel' => $channel,
        'release' => $release,
        'tag' => $tag,
        'version' => datadock_release_version($tag),
        'asset_url' => datadock_release_asset_url($release),
        'notes' => (string) ($release['body'] ?? ''),
    ];
}

/**
 * Install a release zip that is already on disk.
 * Settings, the database config, the update channel, and upload storage are left in place.
 *
 * @return array{ok:bool,error:?string,label:string}
 */
function datadock_install_release_zip(string $zipPath, string $appRoot): array {
    if (!function_exists('datadock_archive_entry_is_safe')) {
        require_once __DIR__ . '/functions.php';
    }
    if (!function_exists('datadock_remove_tree')) {
        require_once __DIR__ . '/private_storage.php';
    }
    $failed = static function (string $error): array {
        return ['ok' => false, 'error' => $error, 'label' => ''];
    };
    if (!class_exists('ZipArchive')) {
        return $failed('Zip support is not installed.');
    }
    if (!is_file($zipPath) || filesize($zipPath) === 0) {
        return $failed('The release zip is empty or missing.');
    }
    $appReal = realpath($appRoot);
    if ($appReal === false || !is_dir($appReal)) {
        return $failed('The application directory could not be found.');
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return $failed('That file is not a zip archive.');
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = (string) $zip->getNameIndex($i);
        if (!datadock_archive_entry_is_safe($entry)) {
            $zip->close();
            return $failed('The archive contains an unsafe path.');
        }
    }
    $extract = sys_get_temp_dir() . '/datadock-release-' . bin2hex(random_bytes(6));
    if (!@mkdir($extract, 0700, true)) {
        $zip->close();
        return $failed('Could not prepare a temporary directory.');
    }
    $extracted = $zip->extractTo($extract);
    $zip->close();
    if (!$extracted) {
        datadock_remove_tree($extract);
        return $failed('Could not extract the release zip.');
    }
    $source = datadock_release_project_root($extract);
    if ($source === null) {
        datadock_remove_tree($extract);
        return $failed('The archive does not contain a DataDock release (missing index.php).');
    }
    $label = 'the uploaded release';
    $versionFile = $source . '/VERSION';
    if (is_file($versionFile)) {
        $version = trim((string) file_get_contents($versionFile));
        if ($version !== '') {
            $label = $version;
        }
    }
    $skip = [
        'config/settings.php',
        'config/db.php',
        'config/update-channel.txt',
        'config/.db-runtime.php',
        'config/.docker.php',
        'uploads',
        'thumbnails',
    ];
    $sourceReal = realpath($source);
    if ($sourceReal === false) {
        datadock_remove_tree($extract);
        return $failed('The extracted release could not be read.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceReal, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $itemPath = $item->getPathname();
        $rel = substr($itemPath, strlen($sourceReal) + 1);
        $rel = str_replace('\\', '/', $rel);
        if ($rel === '' || datadock_release_path_is_skipped($rel, $skip)) {
            continue;
        }
        $target = $appReal . '/' . $rel;
        $parent = dirname($target);
        if (!is_dir($parent) && !@mkdir($parent, 0755, true)) {
            datadock_remove_tree($extract);
            return $failed('Could not create a directory for the release.');
        }
        $parentReal = realpath($parent);
        $rootPrefix = rtrim($appReal, '/') . '/';
        if ($parentReal === false || ($parentReal !== $appReal && !str_starts_with($parentReal . '/', $rootPrefix))) {
            datadock_remove_tree($extract);
            return $failed('The release tried to write outside the application directory.');
        }
        if ($item->isDir()) {
            if (!is_dir($target) && !@mkdir($target, 0755, true)) {
                datadock_remove_tree($extract);
                return $failed('Could not create a directory for the release.');
            }
            continue;
        }
        if (!@copy($itemPath, $target)) {
            datadock_remove_tree($extract);
            return $failed('Could not copy a file from the release.');
        }
    }
    datadock_remove_tree($extract);
    return ['ok' => true, 'error' => null, 'label' => $label];
}

function datadock_release_project_root(string $extract): ?string {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($extract, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getFilename() === 'index.php') {
            return $file->getPath();
        }
    }
    return null;
}

/**
 * @param list<string> $skip
 */
function datadock_release_path_is_skipped(string $rel, array $skip): bool {
    foreach ($skip as $skipped) {
        $skipped = trim(str_replace('\\', '/', $skipped), '/');
        if ($skipped === '') {
            continue;
        }
        if ($rel === $skipped || str_starts_with($rel, $skipped . '/')) {
            return true;
        }
    }
    return false;
}
