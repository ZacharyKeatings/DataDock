<?php
/**
 * Keep uploaded bytes out of the web root on typical shared hosting.
 *
 * When storage_base_path is empty, the first web request tries to create
 * datadock-storage next to public_html (or www / htdocs / httpdocs) and move
 * uploads/ and thumbnails/ there. If the account cannot write outside the
 * site folder, those directories stay put and get a deny-all .htaccess.
 */

function datadock_htaccess_deny_all(): string {
    return <<<'HTACCESS'
# DataDock serves files through PHP. Do not expose this directory over HTTP.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
HTACCESS;
}

function datadock_app_root(): string {
    $root = realpath(__DIR__ . '/..');
    if ($root === false) {
        $root = dirname(__DIR__);
    }
    return rtrim(str_replace('\\', '/', $root), '/');
}

function datadock_normalize_fs_path(string $path): string {
    return rtrim(str_replace('\\', '/', $path), '/');
}

/**
 * True when $path is $root or a directory inside it.
 */
function datadock_path_is_under(string $path, string $root): bool {
    $path = datadock_normalize_fs_path($path);
    $root = datadock_normalize_fs_path($root);
    if ($path === '' || $root === '' || $root === '/' || $root === '.') {
        return false;
    }
    return $path === $root || str_starts_with($path . '/', $root . '/');
}

/**
 * Account directory that sits above the web root.
 * public_html/addon and www/blog both resolve to the account home, not to a folder the site can serve.
 */
function datadock_private_storage_parent(string $documentRoot): ?string {
    $doc = datadock_normalize_fs_path($documentRoot);
    if ($doc === '' || $doc === '/' || $doc === '.') {
        return null;
    }
    if (preg_match('#^(.*?)/(public_html|www|htdocs|httpdocs)(/|$)#', $doc, $match)) {
        $home = $match[1];
        if ($home !== '' && $home !== '/' && $home !== '.') {
            return $home;
        }
        return null;
    }
    $parent = dirname($doc);
    if ($parent === $doc || $parent === '/' || $parent === '.' || $parent === '') {
        return null;
    }
    return datadock_normalize_fs_path($parent);
}

function datadock_document_root_path(): string {
    $doc = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if (!is_string($doc) || $doc === '') {
        return '';
    }
    $real = realpath($doc);
    if ($real === false) {
        return datadock_normalize_fs_path($doc);
    }
    return datadock_normalize_fs_path($real);
}

function datadock_ensure_denied_htaccess(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    $path = rtrim($dir, '/\\') . '/.htaccess';
    $body = datadock_htaccess_deny_all();
    if (is_file($path)) {
        $current = @file_get_contents($path);
        if ($current === $body) {
            return;
        }
    }
    @file_put_contents($path, $body, LOCK_EX);
}

/**
 * @param array<string, mixed> $settings
 */
function datadock_protect_storage_dirs(array $settings): void {
    $app = datadock_app_root();
    $dirs = [$app . '/uploads', $app . '/thumbnails'];
    $configured = trim((string) ($settings['storage_base_path'] ?? ''));
    if ($configured !== '') {
        $base = $configured[0] === '/' || (PHP_OS_FAMILY === 'Windows' && preg_match('#^[A-Za-z]:[/\\\\]#', $configured))
            ? datadock_normalize_fs_path($configured)
            : datadock_normalize_fs_path($app . '/' . ltrim($configured, '/'));
        $dirs[] = $base . '/uploads';
        $dirs[] = $base . '/thumbnails';
    }
    foreach ($dirs as $dir) {
        datadock_ensure_denied_htaccess($dir);
    }
}

function datadock_patch_storage_base_path_source(string $raw, string $path): ?string {
    $exported = var_export($path, true);
    $count = 0;
    $updated = preg_replace_callback(
        '/([\'"]storage_base_path[\'"]\s*=>\s*)(?:\'(?:\\\\\'|[^\'])*\'|"(?:\\\\"|[^"])*"|[^,\n]+)/',
        static function (array $match) use ($exported): string {
            return $match[1] . $exported;
        },
        $raw,
        1,
        $count
    );
    if ($count !== 1 || !is_string($updated) || $updated === $raw) {
        return null;
    }
    return $updated;
}

function datadock_copy_tree(string $src, string $dest): bool {
    if (!is_dir($dest) && !@mkdir($dest, 0755, true) && !is_dir($dest)) {
        return false;
    }
    $items = @scandir($src);
    if ($items === false) {
        return false;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $from = $src . '/' . $item;
        $to = $dest . '/' . $item;
        if (is_link($from) || is_file($from)) {
            if (is_file($to)) {
                continue;
            }
            if (!@copy($from, $to)) {
                return false;
            }
        } elseif (is_dir($from)) {
            if (!datadock_copy_tree($from, $to)) {
                return false;
            }
        }
    }
    return true;
}

function datadock_remove_tree(string $path): bool {
    if (is_link($path) || is_file($path)) {
        return @unlink($path);
    }
    if (!is_dir($path)) {
        return true;
    }
    $items = @scandir($path);
    if ($items === false) {
        return false;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (!datadock_remove_tree($path . '/' . $item)) {
            return false;
        }
    }
    return @rmdir($path);
}

/**
 * Place one directory at $dest.
 * Returns 'rename', 'copy', or 'missing' on success, or null on failure.
 */
function datadock_stage_storage_dir(string $src, string $dest): ?string {
    if (!is_dir($src)) {
        if (!is_dir($dest) && !@mkdir($dest, 0755, true) && !is_dir($dest)) {
            return null;
        }
        return 'missing';
    }
    $srcReal = realpath($src);
    $destReal = realpath($dest);
    if ($srcReal !== false && $destReal !== false && $srcReal === $destReal) {
        return 'missing';
    }
    if (!is_dir($dest)) {
        $parent = dirname($dest);
        if (!is_dir($parent) && !@mkdir($parent, 0700, true) && !is_dir($parent)) {
            return null;
        }
        if (@rename($src, $dest)) {
            return 'rename';
        }
    }
    if (!datadock_copy_tree($src, $dest)) {
        return null;
    }
    return 'copy';
}

/**
 * Move the default uploads and thumbnails trees and point settings at $dest.
 * Settings are updated before a copied source is removed. A renamed directory is moved back if settings cannot be saved.
 */
function datadock_commit_private_storage(string $appRoot, string $dest, string $settingsFile): bool {
    $appRoot = datadock_normalize_fs_path($appRoot);
    $dest = datadock_normalize_fs_path($dest);
    if ($appRoot === '' || $dest === '' || datadock_path_is_under($dest, $appRoot)) {
        return false;
    }
    if ((!is_dir($dest) && !@mkdir($dest, 0700, true)) || !is_dir($dest) || !is_writable($dest)) {
        return false;
    }
    @chmod($dest, 0700);
    $probe = $dest . '/.write-probe';
    if (@file_put_contents($probe, 'ok') === false) {
        return false;
    }
    @unlink($probe);

    $staged = [];
    foreach (['uploads', 'thumbnails'] as $leaf) {
        $how = datadock_stage_storage_dir($appRoot . '/' . $leaf, $dest . '/' . $leaf);
        if ($how === null) {
            foreach ($staged as $doneLeaf => $doneHow) {
                if ($doneHow === 'rename' && is_dir($dest . '/' . $doneLeaf) && !is_dir($appRoot . '/' . $doneLeaf)) {
                    @rename($dest . '/' . $doneLeaf, $appRoot . '/' . $doneLeaf);
                }
            }
            return false;
        }
        $staged[$leaf] = $how;
    }
    if (!is_dir($dest . '/uploads/avatars')) {
        @mkdir($dest . '/uploads/avatars', 0755, true);
    }
    datadock_ensure_denied_htaccess($dest . '/uploads');
    datadock_ensure_denied_htaccess($dest . '/thumbnails');

    $raw = @file_get_contents($settingsFile);
    $updated = $raw === false ? null : datadock_patch_storage_base_path_source($raw, $dest);
    if ($updated === null || @file_put_contents($settingsFile, $updated, LOCK_EX) === false) {
        foreach ($staged as $leaf => $how) {
            if ($how === 'rename' && is_dir($dest . '/' . $leaf) && !is_dir($appRoot . '/' . $leaf)) {
                @rename($dest . '/' . $leaf, $appRoot . '/' . $leaf);
            }
        }
        return false;
    }

    foreach ($staged as $leaf => $how) {
        if ($how === 'copy') {
            datadock_remove_tree($appRoot . '/' . $leaf);
        }
        $leftover = $appRoot . '/' . $leaf;
        if (!is_dir($leftover)) {
            @mkdir($leftover, 0755, true);
        }
        datadock_ensure_denied_htaccess($leftover);
    }
    return true;
}

/**
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function datadock_maybe_relocate_private_storage(array $settings): array {
    datadock_protect_storage_dirs($settings);

    if (PHP_SAPI === 'cli') {
        return $settings;
    }
    if (trim((string) ($settings['storage_base_path'] ?? '')) !== '') {
        return $settings;
    }
    $envPath = getenv('DATADOCK_STORAGE_BASE_PATH');
    if ($envPath !== false && $envPath !== '') {
        return $settings;
    }
    if (is_file('/.dockerenv')) {
        return $settings;
    }

    $doc = datadock_document_root_path();
    $app = datadock_app_root();
    if ($doc === '' || !datadock_path_is_under($app, $doc)) {
        return $settings;
    }

    $parent = datadock_private_storage_parent($doc);
    if ($parent === null) {
        return $settings;
    }
    $dest = $parent . '/datadock-storage';
    if (datadock_path_is_under($dest, $doc) || datadock_path_is_under($dest, $app)) {
        return $settings;
    }

    $settingsFile = __DIR__ . '/../config/settings.php';
    $skipFile = __DIR__ . '/../config/.private-storage-skip';
    if (!is_file($settingsFile) || !is_writable($settingsFile)) {
        return $settings;
    }
    if (is_file($skipFile)) {
        $skipped = trim((string) @file_get_contents($skipFile));
        if ($skipped === $dest) {
            return $settings;
        }
    }

    $lockPath = __DIR__ . '/../config/.private-storage.lock';
    $lock = @fopen($lockPath, 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        return $settings;
    }

    try {
        $fresh = datadock_load_settings_from_file();
        $freshPath = trim((string) ($fresh['storage_base_path'] ?? ''));
        if ($freshPath !== '') {
            $settings['storage_base_path'] = $freshPath;
            return $settings;
        }
        if (!is_dir($parent) || !is_writable($parent)) {
            @file_put_contents($skipFile, $dest);
            return $settings;
        }
        if (!datadock_commit_private_storage($app, $dest, $settingsFile)) {
            @file_put_contents($skipFile, $dest);
            return $settings;
        }
        @unlink($skipFile);
        $settings['storage_base_path'] = $dest;
        error_log('DataDock: moved uploads and thumbnails outside the web root.');
        return $settings;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
