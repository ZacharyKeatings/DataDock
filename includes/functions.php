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
        "        'lockout_window' => 10\n" .
        "    ],\n" .
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
        "    ]\n" .
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
        'application/x-rar-compressed' => 'RAR Archive',
        'application/json' => 'JSON File',
        'application/xml' => 'XML File',
        'application/octet-stream' => 'Binary File',

        // Images
        'image/jpeg' => 'JPEG Image',
        'image/png' => 'PNG Image',
        'image/gif' => 'GIF Image',
        'image/webp' => 'WebP Image',
        'image/svg+xml' => 'SVG Image',

        // Audio
        'audio/mpeg' => 'MP3 Audio',
        'audio/wav' => 'WAV Audio',
        'audio/ogg' => 'OGG Audio',

        // Video
        'video/mp4' => 'MP4 Video',
        'video/webm' => 'WebM Video',
        'video/x-msvideo' => 'AVI Video',
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
            'pdf' => 'file-doc', 'doc' => 'file-doc', 'docx' => 'file-doc',
            'xls' => 'file-sheet', 'xlsx' => 'file-sheet', 'ppt' => 'file-slide', 'pptx' => 'file-slide',
            'txt' => 'file-doc', 'json' => 'file-doc', 'xml' => 'file-doc',
            'zip' => 'file-archive', 'rar' => 'file-archive',
            'mp3' => 'file-audio', 'wav' => 'file-audio', 'ogg' => 'file-audio',
            'mp4' => 'file-video', 'webm' => 'file-video', 'avi' => 'file-video',
            'jpg' => 'file-image', 'jpeg' => 'file-image', 'png' => 'file-image', 'gif' => 'file-image', 'webp' => 'file-image', 'svg' => 'file-image',
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