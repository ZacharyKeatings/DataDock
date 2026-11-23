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
