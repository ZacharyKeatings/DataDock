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
 * Additional helper functions can be added here.
 */