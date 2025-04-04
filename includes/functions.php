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
        "    'registration_enabled' => true,\n" .
        "    'max_file_size' => 5242880,\n" .
        "    'brute_force' => [\n" .
        "        'enabled' => true,\n" .
        "        'max_attempts' => 5,\n" .
        "        'lockout_minutes' => 15,\n" .
        "        'lockout_window' => 10\n" .
        "    ],\n" .
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
