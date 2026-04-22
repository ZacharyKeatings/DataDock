<?php
/**
 * Load config/settings.php and apply optional DATADOCK_* environment overrides (containers, 12-factor).
 */

/**
 * @return array<string, mixed>
 */
function datadock_load_settings_from_file(): array {
    $settings = [];
    $path = __DIR__ . '/../config/settings.php';
    if (file_exists($path)) {
        include $path;
    }
    return is_array($settings ?? null) ? $settings : [];
}

function datadock_env_bool(?string $v, ?bool $default = null): ?bool {
    if ($v === null || $v === '') {
        return $default;
    }
    $v = strtolower(trim($v));
    if (in_array($v, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($v, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }
    return $default;
}

/**
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function datadock_apply_env_overrides(array $settings): array {
    $e = static function (string $key): ?string {
        $v = getenv($key);
        if ($v === false) {
            return null;
        }
        return (string) $v;
    };

    if (($v = $e('DATADOCK_SITE_NAME')) !== null && $v !== '') {
        $settings['site_name'] = $v;
    }
    if (($b = datadock_env_bool($e('DATADOCK_READ_ONLY'), null)) !== null) {
        $settings['read_only_mode'] = $b;
    }
    if (($b = datadock_env_bool($e('DATADOCK_MAINTENANCE_MODE'), null)) !== null) {
        $settings['maintenance_mode'] = $b;
    }
    if (($b = datadock_env_bool($e('DATADOCK_DEBUG_MODE'), null)) !== null) {
        $settings['debug_mode'] = $b;
    }
    if (($b = datadock_env_bool($e('DATADOCK_REGISTRATION_ENABLED'), null)) !== null) {
        $settings['registration_enabled'] = $b;
    }
    if (($b = datadock_env_bool($e('DATADOCK_PUBLIC_BROWSING_ENABLED'), null)) !== null) {
        $settings['public_browsing_enabled'] = $b;
    }
    if (($v = $e('DATADOCK_STORAGE_BASE_PATH')) !== null) {
        $settings['storage_base_path'] = $v;
    }
    if (($v = $e('DATADOCK_LOG_PATH')) !== null) {
        $settings['log_path'] = $v;
    }
    if (($v = $e('DATADOCK_LOG_LEVEL')) !== null && $v !== '') {
        $settings['log_level'] = $v;
    }
    if (($v = $e('DATADOCK_MAX_FILE_SIZE')) !== null && $v !== '' && is_numeric($v)) {
        $settings['max_file_size'] = (int) $v;
    }
    if (($v = $e('DATADOCK_SESSION_TIMEOUT_MINUTES')) !== null && $v !== '' && is_numeric($v)) {
        $settings['session_timeout_minutes'] = (int) $v;
    }
    if (($v = $e('DATADOCK_SECURITY_HEADERS')) !== null && $v !== '') {
        $vm = strtolower(trim($v));
        if (in_array($vm, ['off', 'recommended', 'strict'], true)) {
            $settings['security_headers_mode'] = $vm;
        }
    }
    if (($b = datadock_env_bool($e('DATADOCK_GUEST_UPLOADS_ENABLED'), null)) !== null) {
        if (!isset($settings['guest_uploads']) || !is_array($settings['guest_uploads'])) {
            $settings['guest_uploads'] = ['enabled' => false, 'max_files' => 0, 'max_storage' => 0];
        }
        $settings['guest_uploads']['enabled'] = $b;
    }
    if (($b = datadock_env_bool($e('DATADOCK_INVITE_ONLY_REGISTRATION'), null)) !== null) {
        $settings['invite_only_registration'] = $b;
    }

    return $settings;
}

/**
 * Cached merged settings (file + env). Use datadock_load_settings_from_file() in Admin when editing stored values.
 *
 * @return array<string, mixed>
 */
function datadock_load_settings(): array {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = datadock_apply_env_overrides(datadock_load_settings_from_file());
    return $cached;
}

/**
 * @param array<string, mixed> $settings
 */
function datadock_read_only_enabled(array $settings): bool {
    return !empty($settings['read_only_mode']);
}
