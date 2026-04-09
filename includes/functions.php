<?php
/**
 * Sanitize input to prevent XSS attacks.
 *
 * @param string $data
 * @return string
 */
function sanitize_data($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Output a username as a link to the public profile, or plain text for Guest/empty.
 *
 * @param string|null $username Username to display and link
 * @return string HTML (link or escaped text)
 */
function user_profile_link($username) {
    $username = $username === null ? '' : trim($username);
    if ($username === '' || strtolower($username) === 'guest') {
        return sanitize_data($username ?: 'Guest');
    }
    return '<a href="user.php?username=' . urlencode($username) . '">' . sanitize_data($username) . '</a>';
}

/**
 * Output inline SVG icon from sprite.
 *
 * @param string $name  Icon name (e.g. 'upload', 'folder', 'icon-sun')
 * @param string $class Optional CSS class
 * @return string HTML for the icon
 */
function icon_svg($name, $class = '') {
    $id = strpos($name, 'icon-') === 0 ? $name : 'icon-' . $name;
    $cls = trim('icon ' . $class);
    return '<svg class="' . htmlspecialchars($cls) . '" aria-hidden="true" width="24" height="24"><use href="assets/icons.svg#' . htmlspecialchars($id) . '"/></svg>';
}

/**
 * Resolve storage base path (absolute). Empty = use project root.
 *
 * @return string Base directory path (no trailing slash for subdir appending)
 */
function _resolve_storage_base(): string {
    static $base = null;
    if ($base === null) {
        $settingsPath = __DIR__ . '/../config/settings.php';
        $settings = file_exists($settingsPath) ? require $settingsPath : [];
        $cfg = trim($settings['storage_base_path'] ?? '');
        if (empty($cfg)) {
            $base = rtrim(str_replace('\\', '/', __DIR__ . '/..'), '/');
        } else {
            $base = (strpos($cfg, '/') === 0) ? rtrim($cfg, '/') : rtrim(str_replace('\\', '/', __DIR__ . '/../' . ltrim($cfg, '/')), '/');
        }
    }
    return $base;
}

/**
 * Get the base path for file uploads (absolute path, trailing slash).
 *
 * @return string
 */
function get_upload_path(): string {
    return _resolve_storage_base() . '/uploads/';
}

/**
 * Get the base path for thumbnails (absolute path, trailing slash).
 *
 * @return string
 */
function get_thumbnails_path(): string {
    return _resolve_storage_base() . '/thumbnails/';
}

/**
 * Get the base path for avatar uploads (absolute path, trailing slash).
 *
 * @return string
 */
function get_avatars_path(): string {
    return _resolve_storage_base() . '/uploads/avatars/';
}



/**
 * Retrieves the site name from the settings file.
 *
 * If the settings file is missing or does not contain a valid site name,
 * the function returns the default name "DataDock".
 *
 * @return string The configured site name or "DataDock" if not found.
 */
function get_site_name(): string {
    $settingsPath = __DIR__ . '/../config/settings.php';

    if (!file_exists($settingsPath)) {
        return 'DataDock';
    }

    // Load settings without polluting global scope
    $settings = [];
    include $settingsPath;

    return isset($settings['site_name']) && is_string($settings['site_name'])
        ? $settings['site_name']
        : 'DataDock';
}



/**
 * Returns the current page's basename.
 *
 * @return string
 */
function get_current_page() {
    return basename($_SERVER['PHP_SELF']);
}

/**
 * Absolute path from the web root to a PHP script in the same directory as the current script.
 * Use for form actions and redirects when the app lives in a subdirectory or URLs are rewritten
 * (relative filenames can resolve to the wrong path and cause 404s on POST).
 */
function app_script_url(string $filename): string {
    $filename = ltrim(str_replace('\\', '/', $filename), '/');
    $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '/index.php');
    $script = str_replace('\\', '/', (string) $script);
    $dir = dirname($script);
    if ($dir === '/' || $dir === '.' || $dir === '') {
        return '/' . $filename;
    }
    return rtrim($dir, '/') . '/' . $filename;
}

function return_bytes(string $val): int {
    $val = trim($val);
    $last = strtolower($val[strlen($val) - 1]);
    $num  = (int) $val;
    switch ($last) {
        case 'g': $num *= 1024;
        case 'm': $num *= 1024;
        case 'k': $num *= 1024;
    }
    return $num;
}

/**
 * Convert a byte count to a display value and unit for forms.
 *
 * @param int $bytes Size in bytes.
 * @return array{0: float, 1: string} [value, unit] where unit is 'b','k','m','g'
 */
function bytes_to_display(int $bytes): array {
    $bytes = (int) $bytes;
    if ($bytes >= 1073741824) {
        return [round($bytes / 1073741824, 2), 'g'];
    }
    if ($bytes >= 1048576) {
        return [round($bytes / 1048576, 2), 'm'];
    }
    if ($bytes >= 1024) {
        return [round($bytes / 1024, 2), 'k'];
    }
    return [$bytes, 'b'];
}

/**
 * Convert form value + unit to bytes (supports decimals e.g. 1.5 MB).
 *
 * @param float|string $value Numeric value from form.
 * @param string $unit One of 'b','k','m','g'
 * @return int Size in bytes.
 */
function form_size_to_bytes($value, string $unit): int {
    $value = (float) $value;
    $unit = strtolower(trim($unit));
    if (!in_array($unit, ['b', 'k', 'm', 'g'], true)) {
        $unit = 'b';
    }
    switch ($unit) {
        case 'g': $value *= 1024;
        case 'm': $value *= 1024;
        case 'k': $value *= 1024;
    }
    return (int) round($value);
}

/**
 * Convert byte count to PHP ini size string (e.g. 64M, 1G).
 *
 * @param int $bytes Size in bytes.
 * @return string Value suitable for upload_max_filesize / post_max_size.
 */
function bytes_to_ini_size(int $bytes): string {
    $bytes = (int) $bytes;
    if ($bytes <= 0) {
        return '0';
    }
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824) . 'G';
    }
    if ($bytes >= 1048576) {
        return round($bytes / 1048576) . 'M';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . 'K';
    }
    return (string) $bytes;
}


/**
 * Format a MySQL datetime for display (date on top, time below, no seconds).
 * Returns HTML: a wrapper with .datetime-date and .datetime-time spans.
 *
 * @param string|null $mysqlDatetime e.g. "2025-02-18 14:32:05"
 * @return string HTML or empty string
 */
function format_datetime_display($mysqlDatetime) {
    if ($mysqlDatetime === null || $mysqlDatetime === '') {
        return '';
    }
    $t = strtotime($mysqlDatetime);
    if (!$t) {
        return '';
    }
    $date = date('M j, Y', $t);
    $time = date('g:i A', $t);
    return '<span class="datetime-wrap"><span class="datetime-date">' . htmlspecialchars($date) . '</span><span class="datetime-time">' . htmlspecialchars($time) . '</span></span>';
}

/**
 * Format file size in human-readable format.
 *
 * @param int $size Size in bytes.
 * @return string
 */
function format_filesize($size) {
    if ($size < 1024) {
        return $size . ' B';
    } elseif ($size < 1048576) {
        return round($size / 1024, 2) . ' KB';
    } elseif ($size < 1073741824) {
        return round($size / 1048576, 2) . ' MB';
    } else {
        return round($size / 1073741824, 2) . ' GB';
    }
}



/**
 * Generate the default settings.php file with initial site configuration.
 *
 * @param string $siteName The name of the site to be set in the settings file.
 * @return void
 */
function write_default_settings_file($siteName = 'DataDock') {
    $settings = "<?php\n\$settings = [\n" .
        "    'site_name' => " . var_export($siteName, true) . ",\n" .
        "    'admin_contact_email' => '',\n" .
        "    'registration_enabled' => true,\n" .
        "    'invite_only_registration' => false,\n" .
        "    'enforce_unique_email' => true,\n" .
        "    'max_file_size' => 5242880,\n" .
        "    'default_file_expiry' => 'never',\n" .
        "    'thumbnails_enabled' => true,\n" .
        "    'session_timeout_minutes' => 60,\n" .
        "    'install_warning_enabled' => true,\n" .
        "    'maintenance_mode' => false,\n" .
        "    'debug_mode' => false,\n" .
        "    'log_path' => '',\n" .
        "    'log_level' => 'warning',\n" .
        "    'logo_url' => '',\n" .
        "    'favicon_url' => '',\n" .
        "    'welcome_message' => '',\n" .
        "    'theme' => 'light',\n" .
        "    'file_icons' => [],\n" .
        "    'tos_enabled' => false,\n" .
        "    'tos_text' => '',\n" .
        "    'brute_force' => [\n" .
        "        'enabled' => true,\n" .
        "        'max_attempts' => 5,\n" .
        "        'lockout_minutes' => 15,\n" .
        "        'lockout_window' => 10,\n" .
        "        'adaptive_cooldown_enabled' => true,\n" .
        "        'adaptive_cooldown_ip_window_minutes' => 60,\n" .
        "        'adaptive_cooldown_steps' => [5 => 5, 15 => 15, 30 => 60]\n" .
        "    ],\n" .
        "    'rate_limit_uploads' => [\n" .
        "        'enabled' => false,\n" .
        "        'window_minutes' => 1,\n" .
        "        'max_per_ip' => 30,\n" .
        "        'max_per_user' => 60\n" .
        "    ],\n" .
        "    'rewrite_file_extension' => false,\n" .
        "    'upload_quarantine_enabled' => false,\n" .
        "    'storage_base_path' => '',\n" .
        "    'public_browsing_enabled' => false,\n" .
        "    'guest_uploads' => [\n" .
        "        'enabled' => false,\n" .
        "        'max_files' => 0,\n" .
        "        'max_storage' => 0\n" .
        "    ],\n" .
        "    'user_limits' => [\n" .
        "        'max_files_enabled' => false,\n" .
        "        'max_files' => 100,\n" .
        "        'max_storage_enabled' => false,\n" .
        "        'max_storage' => 104857600\n" . // 100MB
        "    ],\n" .
        "    'trash_retention_days' => 30,\n" .
        "    'deduplicate_storage' => true,\n" .
        "    'folders_enabled' => true,\n" .
        "    'tags_enabled' => true\n" .
        "];\n?>";

    file_put_contents(__DIR__ . '/../config/settings.php', $settings);
}



/**
 * Write a message to the log file if logging is configured.
 *
 * @param string $level   Log level: debug, info, warning, error
 * @param string $message Message to log
 * @return bool True if logged, false otherwise
 */
function log_message($level, $message) {
    static $settings = null;
    if ($settings === null) {
        $path = __DIR__ . '/../config/settings.php';
        $settings = file_exists($path) ? require $path : [];
    }
    $logPath = trim($settings['log_path'] ?? '');
    $logLevel = strtolower($settings['log_level'] ?? 'warning');
    $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
    $minLevel = $levels[$logLevel] ?? 2;
    $msgLevel = $levels[$level] ?? 2;
    if (empty($logPath) || $msgLevel < $minLevel) {
        return false;
    }
    $absPath = strpos($logPath, '/') === 0 ? $logPath : (__DIR__ . '/../' . ltrim($logPath, '/'));
    $dir = dirname($absPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] [$level] $message\n";
    return @file_put_contents($absPath, $line, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * Risky MIME types that must never be served inline (force download to prevent execution).
 *
 * @return string[] MIME type prefixes or exact types
 */
function get_risky_inline_mimes(): array {
    return [
        'text/html', 'text/xml', 'application/xml', 'image/svg+xml',
        'application/javascript', 'text/javascript', 'application/json',
        'application/pdf', 'application/x-shockwave-flash',
    ];
}

/**
 * Whether the given MIME should always use Content-Disposition: attachment (no inline).
 *
 * @param string $mime
 * @return bool
 */
function is_risky_mime_for_inline(string $mime): bool {
    $mime = strtolower(trim($mime));
    foreach (get_risky_inline_mimes() as $risky) {
        if ($mime === $risky || str_starts_with($mime, $risky . ';') || str_starts_with($mime, $risky . ' ')) {
            return true;
        }
    }
    return false;
}

/**
 * Check if file extension matches expected MIME (for anomaly detection).
 * Returns true if mismatch (anomaly), false if extension matches common MIME for that extension.
 *
 * @param string $extension Lowercase file extension
 * @param string $detectedMime Detected MIME type (e.g. from mime_content_type)
 * @return bool True when extension vs MIME is suspicious
 */
function mime_extension_mismatch(string $extension, string $detectedMime): bool {
    static $map = null;
    if ($map === null) {
        $map = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'bmp' => ['image/bmp', 'image/x-ms-bmp'],
            'tiff' => ['image/tiff'],
            'tif' => ['image/tiff'],
            'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'],
            'svg' => ['image/svg+xml'],
            'heic' => ['image/heic'],
            'avif' => ['image/avif'],
            'pdf' => ['application/pdf'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
            'rar' => ['application/vnd.rar', 'application/x-rar-compressed'],
            '7z' => ['application/x-7z-compressed'],
            'tar' => ['application/x-tar'],
            'gz' => ['application/gzip', 'application/x-gzip'],
            'txt' => ['text/plain'],
            'html' => ['text/html'],
            'htm' => ['text/html'],
            'json' => ['application/json'],
            'xml' => ['application/xml', 'text/xml'],
            'csv' => ['text/csv', 'text/plain', 'application/csv'],
            'rtf' => ['application/rtf', 'text/rtf'],
            'mp3' => ['audio/mpeg', 'audio/mp3'],
            'wav' => ['audio/wav', 'audio/x-wav'],
            'ogg' => ['audio/ogg'],
            'flac' => ['audio/flac', 'audio/x-flac'],
            'm4a' => ['audio/mp4', 'audio/x-m4a'],
            'mp4' => ['video/mp4'],
            'mov' => ['video/quicktime'],
            'webm' => ['video/webm'],
            'mkv' => ['video/x-matroska'],
            'avi' => ['video/x-msvideo', 'video/avi'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ppt' => ['application/vnd.ms-powerpoint'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'odt' => ['application/vnd.oasis.opendocument.text'],
            'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
            'odp' => ['application/vnd.oasis.opendocument.presentation'],
            'epub' => ['application/epub+zip'],
        ];
    }
    $detectedMime = strtolower(trim($detectedMime));
    if ($detectedMime === '' || $detectedMime === 'application/octet-stream') {
        return false;
    }
    if (!isset($map[$extension])) {
        return false;
    }
    return !in_array($detectedMime, $map[$extension], true);
}

/**
 * Executable / server-side extensions that must not appear as any dot segment in the basename
 * (blocks evil.php.jpg, shell.php.png, etc.).
 *
 * @return string[]
 */
function get_forbidden_upload_extensions(): array {
    static $list = null;
    if ($list !== null) {
        return $list;
    }
    $list = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8',
        'phtml', 'phar', 'phps', 'pht', 'pgif',
        'exe', 'sh', 'bat', 'cmd', 'com', 'scr',
        'js', 'mjs', 'cjs', 'pl', 'py', 'pyc', 'pyo', 'cgi',
        'asp', 'aspx', 'ashx', 'cer', 'jsp', 'jspf', 'jspx',
        'vbs', 'vbe', 'wsf', 'dll', 'msi', 'hta', 'htc',
    ];
    return $list;
}

/**
 * True if any segment of the filename (split on ".") is a forbidden extension.
 */
function upload_basename_has_dangerous_extension_segment(string $filename): bool {
    $base = basename(str_replace('\\', '/', $filename));
    $base = rtrim($base, '.');
    if ($base === '' || strpos($base, '.') === false) {
        return false;
    }
    $parts = explode('.', strtolower($base));
    $forbidden = array_flip(get_forbidden_upload_extensions());
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (isset($forbidden[$part])) {
            return true;
        }
    }
    return false;
}

/**
 * For common image extensions, require MIME and magic bytes to match the extension.
 * Rejects polyglots such as a GIF body saved as .jpg (mime_content_type is usually still correct).
 *
 * @return bool True if OK to accept, false if upload should be rejected
 */
function upload_strict_image_format_ok(string $tmpPath, string $extensionLower, string $mimeType): bool {
    $mimeType = strtolower(trim($mimeType));
    $head = @file_get_contents($tmpPath, false, null, 0, 32);
    if ($head === false || $head === '') {
        return false;
    }
    switch ($extensionLower) {
        case 'jpg':
        case 'jpeg':
            if (!in_array($mimeType, ['image/jpeg'], true)) {
                return false;
            }
            return strlen($head) >= 3 && $head[0] === "\xFF" && $head[1] === "\xD8" && $head[2] === "\xFF";
        case 'png':
            if (!in_array($mimeType, ['image/png'], true)) {
                return false;
            }
            return strncmp($head, "\x89PNG\r\n\x1a\n", 8) === 0;
        case 'gif':
            if (!in_array($mimeType, ['image/gif'], true)) {
                return false;
            }
            return strncmp($head, 'GIF87a', 6) === 0 || strncmp($head, 'GIF89a', 6) === 0;
        case 'webp':
            if (!in_array($mimeType, ['image/webp'], true)) {
                return false;
            }
            return strlen($head) >= 12 && substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP';
        case 'bmp':
            if (!in_array($mimeType, ['image/bmp', 'image/x-ms-bmp'], true)) {
                return false;
            }
            return strlen($head) >= 2 && strncmp($head, 'BM', 2) === 0;
        case 'tif':
        case 'tiff':
            if (!in_array($mimeType, ['image/tiff'], true)) {
                return false;
            }
            return strlen($head) >= 4 && (strncmp($head, "II*\x00", 4) === 0 || strncmp($head, "MM\x00*", 4) === 0);
        case 'ico':
            if (!in_array($mimeType, ['image/x-icon', 'image/vnd.microsoft.icon'], true)) {
                return false;
            }
            return strlen($head) >= 4
                && (strncmp($head, "\0\0\1\0", 4) === 0 || strncmp($head, "\0\0\2\0", 4) === 0);
        case 'svg':
            return in_array($mimeType, ['image/svg+xml'], true);
        case 'heic':
            return in_array($mimeType, ['image/heic'], true);
        case 'avif':
            return in_array($mimeType, ['image/avif'], true);
        default:
            return true;
    }
}

/**
 * True if a binary image appears to embed PHP / short-open tags (GIF/JPEG/WEBP polyglot webshells).
 * Skips SVG (XML) and non-image MIME types.
 */
function upload_image_body_has_embedded_script_markers(string $tmpPath, string $mimeType, string $extensionLower): bool {
    $mimeType = strtolower(trim($mimeType));
    $isSvg = strpos($mimeType, 'svg') !== false || strtolower($extensionLower) === 'svg';
    if (!str_starts_with($mimeType, 'image/') && !$isSvg) {
        return false;
    }
    $size = @filesize($tmpPath);
    if ($size === false || $size < 1) {
        return false;
    }
    $max = $isSvg ? (int) min(1048576, $size) : (int) min(524288, $size);
    $chunk = @file_get_contents($tmpPath, false, null, 0, $max);
    if ($chunk === false) {
        return true;
    }
    if (stripos($chunk, '<?php') !== false || stripos($chunk, '<?=') !== false) {
        return true;
    }
    if (preg_match('/<\?(?!xml[\s=])/i', $chunk)) {
        return true;
    }
    return false;
}

function get_friendly_filetype($mime) {
    $map = [
        'application/pdf' => 'PDF Document',
        'application/msword' => 'Word Document (Legacy)',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word Document',
        'application/vnd.ms-excel' => 'Excel Spreadsheet (Legacy)',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel Spreadsheet',
        'application/vnd.ms-powerpoint' => 'PowerPoint Presentation (Legacy)',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PowerPoint Presentation',
        'text/plain' => 'Plain Text File',
        'text/html' => 'HTML Document',
        'application/zip' => 'ZIP Archive',
        'application/x-zip-compressed' => 'ZIP Archive',
        'application/vnd.rar' => 'RAR Archive',
        'application/x-rar-compressed' => 'RAR Archive',
        'application/x-7z-compressed' => '7-Zip Archive',
        'application/x-tar' => 'TAR Archive',
        'application/gzip' => 'GZIP Archive',
        'application/x-gzip' => 'GZIP Archive',
        'application/json' => 'JSON File',
        'application/xml' => 'XML File',
        'application/rtf' => 'RTF Document',
        'text/csv' => 'CSV Spreadsheet',
        'application/csv' => 'CSV Spreadsheet',
        'application/octet-stream' => 'Binary File',
        'application/epub+zip' => 'EPUB E-book',
        'application/vnd.oasis.opendocument.text' => 'OpenDocument Text',
        'application/vnd.oasis.opendocument.spreadsheet' => 'OpenDocument Spreadsheet',
        'application/vnd.oasis.opendocument.presentation' => 'OpenDocument Presentation',

        // Images
        'image/jpeg' => 'JPEG Image',
        'image/png' => 'PNG Image',
        'image/gif' => 'GIF Image',
        'image/webp' => 'WebP Image',
        'image/bmp' => 'BMP Image',
        'image/x-ms-bmp' => 'BMP Image',
        'image/tiff' => 'TIFF Image',
        'image/x-icon' => 'ICO Image',
        'image/vnd.microsoft.icon' => 'ICO Image',
        'image/svg+xml' => 'SVG Image',
        'image/heic' => 'HEIC Image',
        'image/avif' => 'AVIF Image',

        // Audio
        'audio/mpeg' => 'MP3 Audio',
        'audio/mp3' => 'MP3 Audio',
        'audio/wav' => 'WAV Audio',
        'audio/x-wav' => 'WAV Audio',
        'audio/ogg' => 'OGG Audio',
        'audio/flac' => 'FLAC Audio',
        'audio/x-flac' => 'FLAC Audio',
        'audio/mp4' => 'M4A Audio',
        'audio/x-m4a' => 'M4A Audio',

        // Video
        'video/mp4' => 'MP4 Video',
        'video/webm' => 'WebM Video',
        'video/x-msvideo' => 'AVI Video',
        'video/avi' => 'AVI Video',
        'video/quicktime' => 'QuickTime Video',
        'video/x-matroska' => 'MKV Video',
    ];

    return $map[$mime] ?? 'Unknown File Type';
}

/**
 * Get icon URL or class for a file based on extension or MIME type.
 * Uses custom file_icons from settings if set, otherwise default mapping.
 *
 * @param string|null $filetype MIME type (e.g. application/pdf)
 * @param string|null $filename Optional original filename for extension fallback
 * @return string Icon URL, emoji, or empty string
 */
function get_file_icon($filetype, $filename = null) {
    static $customIcons = null;
    if ($customIcons === null) {
        $path = __DIR__ . '/../config/settings.php';
        $settings = file_exists($path) ? require $path : [];
        $customIcons = $settings['file_icons'] ?? [];
    }
    $ext = $filename ? strtolower(pathinfo($filename, PATHINFO_EXTENSION)) : '';
    $key = $ext ?: ($filetype ?: '');
    if (!empty($customIcons[$key])) {
        return $customIcons[$key];
    }
    if ($ext && !empty($customIcons[$ext])) {
        return $customIcons[$ext];
    }
    if ($ext) {
        $extMap = [
            'pdf' => 'file-doc', 'doc' => 'file-doc', 'docx' => 'file-doc', 'rtf' => 'file-doc', 'odt' => 'file-doc', 'epub' => 'file-doc',
            'xls' => 'file-sheet', 'xlsx' => 'file-sheet', 'ods' => 'file-sheet', 'csv' => 'file-sheet',
            'ppt' => 'file-slide', 'pptx' => 'file-slide', 'odp' => 'file-slide',
            'txt' => 'file-doc', 'json' => 'file-doc', 'xml' => 'file-doc',
            'zip' => 'file-archive', 'rar' => 'file-archive', '7z' => 'file-archive', 'tar' => 'file-archive', 'gz' => 'file-archive',
            'mp3' => 'file-audio', 'wav' => 'file-audio', 'ogg' => 'file-audio', 'flac' => 'file-audio', 'm4a' => 'file-audio',
            'mp4' => 'file-video', 'webm' => 'file-video', 'avi' => 'file-video', 'mov' => 'file-video', 'mkv' => 'file-video',
            'jpg' => 'file-image', 'jpeg' => 'file-image', 'png' => 'file-image', 'gif' => 'file-image', 'webp' => 'file-image', 'bmp' => 'file-image', 'tiff' => 'file-image', 'tif' => 'file-image', 'ico' => 'file-image', 'svg' => 'file-image', 'heic' => 'file-image', 'avif' => 'file-image',
        ];
        if (isset($extMap[$ext])) return $extMap[$ext];
    }
    if ($filetype) {
        if (str_starts_with($filetype, 'image/')) return 'file-image';
        if (str_starts_with($filetype, 'video/')) return 'file-video';
        if (str_starts_with($filetype, 'audio/')) return 'file-audio';
        if ($filetype === 'application/pdf') return 'file-doc';
    }
    return 'file';
}

/**
 * Build WHERE clause and params for file listing (dashboard, trash, admin).
 * Uses table alias "f" so it works with "SELECT * FROM files f" or "SELECT f.*, u.username FROM files f LEFT JOIN users u ...".
 *
 * @param array $options  user_id (int|null), is_admin (bool), trashed_only (bool), exclude_trashed (bool),
 *                        search (string), date_from (Y-m-d), date_to (Y-m-d), filetype (string),
 *                        visibility ('all'|'public'|'private'), expiry_filter ('all'|'has'|'none'|'expired'|'valid'),
 *                        folder_id (omit | 0 for root only | positive id), tag_id (positive id)
 * @param string $alias   Table alias used in SQL (default 'f')
 * @return array{0: string, 1: array} [WHERE clause fragment, params]
 */
function build_files_list_where(array $options, string $alias = 'f'): array {
    $conditions = [];
    $params = [];
    $pre = $alias ? $alias . '.' : '';

    if (!empty($options['user_id']) && empty($options['is_admin'])) {
        $conditions[] = $pre . 'user_id = ?';
        $params[] = $options['user_id'];
    }

    if (!empty($options['trashed_only'])) {
        $conditions[] = $pre . 'deleted_at IS NOT NULL';
    } elseif (!isset($options['exclude_trashed']) || $options['exclude_trashed']) {
        $conditions[] = $pre . 'deleted_at IS NULL';
    }

    if (!empty($options['search'])) {
        $term = '%' . trim($options['search']) . '%';
        $conditions[] = '(' . $pre . 'original_name LIKE ? OR ' . $pre . 'description LIKE ?)';
        $params[] = $term;
        $params[] = $term;
    }

    if (!empty($options['date_from'])) {
        $conditions[] = $pre . 'upload_date >= ?';
        $params[] = $options['date_from'] . ' 00:00:00';
    }
    if (!empty($options['date_to'])) {
        $conditions[] = $pre . 'upload_date <= ?';
        $params[] = $options['date_to'] . ' 23:59:59';
    }

    if (isset($options['filetype']) && $options['filetype'] !== '') {
        $conditions[] = $pre . 'filetype = ?';
        $params[] = $options['filetype'];
    }

    $vis = $options['visibility'] ?? 'all';
    if ($vis === 'public') {
        $conditions[] = $pre . 'is_public = 1';
    } elseif ($vis === 'private') {
        $conditions[] = $pre . 'is_public = 0';
    }

    $exp = $options['expiry_filter'] ?? 'all';
    if ($exp === 'has') {
        $conditions[] = $pre . 'expiry_date IS NOT NULL';
    } elseif ($exp === 'none') {
        $conditions[] = $pre . 'expiry_date IS NULL';
    } elseif ($exp === 'expired') {
        $conditions[] = $pre . 'expiry_date IS NOT NULL AND ' . $pre . 'expiry_date <= UTC_TIMESTAMP()';
    } elseif ($exp === 'valid') {
        $conditions[] = '(' . $pre . 'expiry_date IS NULL OR ' . $pre . 'expiry_date > UTC_TIMESTAMP())';
    } elseif ($exp === 'valid_has') {
        $conditions[] = $pre . 'expiry_date IS NOT NULL AND ' . $pre . 'expiry_date > UTC_TIMESTAMP()';
    } elseif ($exp === 'valid_none') {
        $conditions[] = $pre . 'expiry_date IS NULL';
    }

    if (array_key_exists('folder_id', $options)) {
        $fid = $options['folder_id'];
        if ($fid === null || $fid === '') {
            // no folder filter
        } elseif ($fid === 0 || $fid === '0') {
            $conditions[] = $pre . 'folder_id IS NULL';
        } else {
            $conditions[] = $pre . 'folder_id = ?';
            $params[] = (int) $fid;
        }
    }

    if (!empty($options['tag_id'])) {
        $conditions[] = 'EXISTS (SELECT 1 FROM file_tags ft WHERE ft.file_id = ' . $pre . 'id AND ft.tag_id = ?)';
        $params[] = (int) $options['tag_id'];
    }

    $where = $conditions ? implode(' AND ', $conditions) : '1=1';
    return [$where, $params];
}

/**
 * Render file icon HTML - either img (for custom URL) or SVG (for built-in icon name).
 *
 * @param string $icon Result from get_file_icon()
 * @param string $class Optional CSS class for the wrapper
 * @return string HTML
 */
function render_file_icon($icon, $class = 'file-icon') {
    if (str_starts_with($icon, 'http')) {
        return '<img src="' . htmlspecialchars($icon) . '" alt="" class="file-icon-img">';
    }
    if (preg_match('/^[a-z0-9-]+$/i', $icon)) {
        return '<span class="' . htmlspecialchars($class) . '">' . icon_svg($icon, 'file-icon-svg') . '</span>';
    }
    return '<span class="' . htmlspecialchars($class) . '">' . sanitize_data($icon) . '</span>';
}



/**
 * Converts a subset of Markdown syntax to basic HTML for safe rendering.
 *
 * Supported Markdown features:
 * - Headings:     # H1, ## H2, ### H3
 * - Bold:         **text**
 * - Italic:       *text*
 * - Inline code:  `code`
 * - Code blocks:  ```multiline code```
 * - Links:        [text](https://example.com)
 * - Lists:
 *     - Unordered: - item or * item
 *     - Ordered:   1. item
 * - Paragraphs:   Two newlines separate blocks
 *
 * HTML output is sanitized to prevent XSS. Intended for displaying Markdown 
 * from trusted sources such as GitHub release notes or changelogs.
 *
 * @param string $text  Raw Markdown content
 * @return string       Safe HTML output
 */
function basic_markdown($text) {
    $text = trim($text);

    // Escape HTML
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Normalize line endings
    $text = preg_replace("/\r\n|\r/", "\n", $text);

    // Handle code blocks (```)
    $text = preg_replace_callback('/```(.*?)```/s', function ($matches) {
        return '<pre><code>' . nl2br($matches[1]) . '</code></pre>';
    }, $text);

    // Inline code (`code`)
    $text = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $text);

    // Bold (**text**)
    $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);

    // Italic (*text*)
    $text = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $text);

    // Horizontal rules
    $text = preg_replace('/^\s*(---|\*\*\*|___)\s*$/m', '<hr>', $text);

    // Headings (###, ##, #)
    $text = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $text);

    // Links: [text](url)
    $text = preg_replace('/\[(.*?)\]\((https?:\/\/[^\s]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $text);

    // Unordered lists
    $text = preg_replace('/^(\s*)[-*] (.+)$/m', '$1<li>$2</li>', $text);
    $text = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $text);

    // Ordered lists
    $text = preg_replace('/^(\s*)\d+\.\s+(.+)$/m', '$1<ol><li>$2</li></ol>', $text);
    $text = preg_replace('/<\/ol>\s*<ol>/', '', $text); // Remove nested <ol><ol>
    $text = preg_replace('/<\/ul>\s*<ul>/', '', $text); // Remove nested <ul><ul>

    // Paragraphs (convert remaining lines into <p>)
    $lines = preg_split('/\n{2,}/', $text);
    foreach ($lines as &$line) {
        if (!preg_match('/^<(h[1-3]|ul|ol|li|pre|p|blockquote)/', $line)) {
            $line = '<p>' . nl2br(trim($line)) . '</p>';
        }
    }

    return implode("\n", $lines);
}

require_once __DIR__ . '/storage.php';