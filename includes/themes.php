<?php
/**
 * Built-in themes, admin-saved custom themes, and the active theme cookie.
 */

function datadock_theme_custom_dir(): string {
    return __DIR__ . '/../assets/themes/custom';
}

/**
 * @return array<string, array{label:string,scheme:string,header:string,background:string,text:string,primary:string,secondary:string,accent:string,border:string,box:string,link:string,link_hover:string,button_text:string,stat:string,font:string,radius:string}>
 */
function datadock_builtin_theme_tokens(): array {
    $system = 'system-ui, -apple-system, "Segoe UI", sans-serif';
    return [
        'midnight' => [
            'label' => 'Midnight', 'scheme' => 'dark', 'font' => $system, 'radius' => '8px',
            'header' => '#0e1530', 'background' => '#0c1224', 'text' => '#e7ecff', 'primary' => '#9aa6ff',
            'secondary' => '#182038', 'accent' => '#f2c14e', 'border' => '#2c3858', 'box' => '#141b30',
            'link' => '#c5ceff', 'link_hover' => '#eef1ff', 'button_text' => '#0c1224', 'stat' => '#c5ceff',
        ],
        'ocean' => [
            'label' => 'Ocean', 'scheme' => 'light', 'font' => $system, 'radius' => '8px',
            'header' => '#0b6e8a', 'background' => '#f3f8fb', 'text' => '#10242c', 'primary' => '#0b6e8a',
            'secondary' => '#e5f1f6', 'accent' => '#f0a202', 'border' => '#c5d7e0', 'box' => '#ffffff',
            'link' => '#0b6e8a', 'link_hover' => '#084e63', 'button_text' => '#ffffff', 'stat' => '#0b6e8a',
        ],
        'forest' => [
            'label' => 'Forest', 'scheme' => 'dark', 'font' => $system, 'radius' => '8px',
            'header' => '#143028', 'background' => '#121c18', 'text' => '#e7f3ec', 'primary' => '#7dcaa0',
            'secondary' => '#1c2e26', 'accent' => '#e6c15a', 'border' => '#2d463a', 'box' => '#17241e',
            'link' => '#b7e4c7', 'link_hover' => '#e8fff0', 'button_text' => '#102018', 'stat' => '#b7e4c7',
        ],
        'sunset' => [
            'label' => 'Sunset', 'scheme' => 'light', 'font' => $system, 'radius' => '10px',
            'header' => '#c4553a', 'background' => '#fff6ef', 'text' => '#2b1b16', 'primary' => '#c4553a',
            'secondary' => '#ffe8dc', 'accent' => '#f0a202', 'border' => '#f0cfc2', 'box' => '#fffaf7',
            'link' => '#a33b24', 'link_hover' => '#7a2a18', 'button_text' => '#ffffff', 'stat' => '#c4553a',
        ],
        'contrast' => [
            'label' => 'High contrast', 'scheme' => 'light', 'font' => $system, 'radius' => '2px',
            'header' => '#000000', 'background' => '#ffffff', 'text' => '#000000', 'primary' => '#000000',
            'secondary' => '#f2f2f2', 'accent' => '#ffd400', 'border' => '#000000', 'box' => '#ffffff',
            'link' => '#0000ee', 'link_hover' => '#551a8b', 'button_text' => '#ffffff', 'stat' => '#000000',
        ],
        'paper' => [
            'label' => 'Paper', 'scheme' => 'light', 'font' => 'Georgia, "Iowan Old Style", "Palatino Linotype", serif', 'radius' => '4px',
            'header' => '#6b5344', 'background' => '#f6f0e6', 'text' => '#2c241c', 'primary' => '#6b5344',
            'secondary' => '#efe4d4', 'accent' => '#c47b2b', 'border' => '#d9cbb8', 'box' => '#fbf7f0',
            'link' => '#6b5344', 'link_hover' => '#3e2e24', 'button_text' => '#ffffff', 'stat' => '#6b5344',
        ],
    ];
}

/**
 * Hand-tuned stylesheets plus generated premade themes.
 *
 * @return array<string, array{label:string,href:string,custom:bool}>
 */
function datadock_theme_catalog(): array {
    if (!function_exists('app_asset_url')) {
        require_once __DIR__ . '/functions.php';
    }
    $catalog = [
        'light' => ['label' => 'Light', 'href' => app_asset_url('assets/themes/light.css'), 'custom' => false],
        'dark' => ['label' => 'Dark', 'href' => app_asset_url('assets/themes/dark.css'), 'custom' => false],
    ];
    foreach (datadock_builtin_theme_tokens() as $slug => $tokens) {
        $catalog[$slug] = [
            'label' => $tokens['label'],
            'href' => app_asset_url('assets/themes/' . $slug . '.css'),
            'custom' => false,
        ];
    }
    foreach (datadock_custom_theme_manifest() as $slug => $row) {
        if (!is_string($slug) || !datadock_theme_slug_ok($slug) || isset($catalog[$slug])) {
            continue;
        }
        $path = datadock_theme_custom_dir() . '/' . $slug . '.css';
        if (!is_file($path)) {
            continue;
        }
        $label = trim((string) ($row['name'] ?? $slug));
        $catalog[$slug] = [
            'label' => $label !== '' ? $label : $slug,
            'href' => app_asset_url('assets/themes/custom/' . $slug . '.css'),
            'custom' => true,
        ];
    }
    return $catalog;
}

function datadock_theme_is_known(string $slug): bool {
    return isset(datadock_theme_catalog()[$slug]);
}

function datadock_theme_stylesheet_url(string $slug): string {
    $catalog = datadock_theme_catalog();
    if (isset($catalog[$slug])) {
        return $catalog[$slug]['href'];
    }
    return $catalog['light']['href'];
}

function datadock_resolve_theme(array $settings): string {
    $catalog = datadock_theme_catalog();
    $loggedIn = session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id']);
    $cookie = isset($_COOKIE['datadock_theme']) ? strtolower(trim((string) $_COOKIE['datadock_theme'])) : '';
    if ($loggedIn && $cookie !== '' && isset($catalog[$cookie])) {
        return $cookie;
    }
    $site = strtolower(trim((string) ($settings['theme'] ?? 'light')));
    if (isset($catalog[$site])) {
        return $site;
    }
    return 'light';
}

function datadock_set_theme_cookie(string $slug): void {
    setcookie('datadock_theme', $slug, [
        'expires' => time() + 86400 * 365,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['datadock_theme'] = $slug;
}

function datadock_clear_theme_cookie(): void {
    setcookie('datadock_theme', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE['datadock_theme']);
}

function datadock_store_user_theme(?string $slug): void {
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['user_id'])) {
        return;
    }
    if (!isset($GLOBALS['pdo']) || !$GLOBALS['pdo'] instanceof PDO) {
        $db = __DIR__ . '/db.php';
        if (is_readable($db)) {
            require_once $db;
        }
    }
    if (!isset($GLOBALS['pdo']) || !$GLOBALS['pdo'] instanceof PDO) {
        return;
    }
    try {
        $GLOBALS['pdo']->prepare('UPDATE users SET preferred_theme = ? WHERE id = ?')->execute([
            $slug,
            (int) $_SESSION['user_id'],
        ]);
    } catch (Throwable $e) {
    }
}

function datadock_theme_slug_ok(string $slug): bool {
    return (bool) preg_match('/^[a-z0-9-]{1,40}$/', $slug);
}

function datadock_theme_slug_from_name(string $name): string {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if (strlen($slug) > 40) {
        $slug = rtrim(substr($slug, 0, 40), '-');
    }
    return $slug;
}

function datadock_hex_color(string $value, string $fallback): string {
    $value = trim($value);
    if (preg_match('/^#([0-9a-fA-F]{6})$/', $value)) {
        return strtolower($value);
    }
    if (preg_match('/^#([0-9a-fA-F]{3})$/', $value)) {
        $h = strtolower($value);
        return '#' . $h[1] . $h[1] . $h[2] . $h[2] . $h[3] . $h[3];
    }
    return $fallback;
}

function datadock_shift_hex(string $hex, int $amount): string {
    $hex = ltrim(datadock_hex_color($hex, '#000000'), '#');
    $channels = [];
    for ($i = 0; $i < 3; $i++) {
        $channels[] = max(0, min(255, hexdec(substr($hex, $i * 2, 2)) + $amount));
    }
    return sprintf('#%02x%02x%02x', $channels[0], $channels[1], $channels[2]);
}

/**
 * @param array<string, string> $tokens
 */
function datadock_render_theme_css(array $tokens, string $extra = ''): string {
    $scheme = ($tokens['scheme'] ?? 'light') === 'dark' ? 'dark' : 'light';
    $header = datadock_hex_color((string) ($tokens['header'] ?? ''), $scheme === 'dark' ? '#0f3d42' : '#038c96');
    $background = datadock_hex_color((string) ($tokens['background'] ?? ''), $scheme === 'dark' ? '#1e2426' : '#f7f6f4');
    $text = datadock_hex_color((string) ($tokens['text'] ?? ''), $scheme === 'dark' ? '#e7eeef' : '#111111');
    $primary = datadock_hex_color((string) ($tokens['primary'] ?? ''), $scheme === 'dark' ? '#4db8c0' : '#038c96');
    $secondary = datadock_hex_color((string) ($tokens['secondary'] ?? ''), $scheme === 'dark' ? '#2c3638' : '#e7e4e0');
    $accent = datadock_hex_color((string) ($tokens['accent'] ?? ''), '#ffbc42');
    $border = datadock_hex_color((string) ($tokens['border'] ?? ''), $scheme === 'dark' ? '#3d4c4e' : '#cccccc');
    $box = datadock_hex_color((string) ($tokens['box'] ?? ''), $scheme === 'dark' ? '#262e30' : '#ffffff');
    $link = datadock_hex_color((string) ($tokens['link'] ?? ''), $primary);
    $linkHover = datadock_hex_color((string) ($tokens['link_hover'] ?? ''), datadock_shift_hex($link, $scheme === 'dark' ? 40 : -30));
    $buttonText = datadock_hex_color((string) ($tokens['button_text'] ?? ''), $scheme === 'dark' ? '#102018' : '#ffffff');
    $stat = datadock_hex_color((string) ($tokens['stat'] ?? ''), $link);
    $font = trim((string) ($tokens['font'] ?? ''));
    if ($font === '' || $font === 'system') {
        $font = 'system-ui, -apple-system, "Segoe UI", sans-serif';
    }
    if (preg_match('/[{};<>]|expression|javascript/i', $font)) {
        $font = 'system-ui, -apple-system, "Segoe UI", sans-serif';
    }
    $radius = trim((string) ($tokens['radius'] ?? '8px'));
    if (!preg_match('/^\d{1,2}px$/', $radius)) {
        $radius = '8px';
    }
    $hover = datadock_shift_hex($primary, $scheme === 'dark' ? 28 : -24);
    $rowAlt = $scheme === 'dark' ? 'rgba(255, 255, 255, 0.04)' : 'rgba(0, 0, 0, 0.04)';
    $css = ":root {\n"
        . "    color-scheme: {$scheme};\n"
        . "    --color-header-background: {$header};\n"
        . "    --color-background: {$background};\n"
        . "    --color-text: {$text};\n"
        . "    --color-primary: {$primary};\n"
        . "    --color-secondary: {$secondary};\n"
        . "    --color-accent: {$accent};\n"
        . "    --color-border: {$border};\n"
        . "    --color-box-background: {$box};\n"
        . "    --color-link: {$link};\n"
        . "    --color-file-row-alt: {$rowAlt};\n"
        . "    --font-family: {$font};\n"
        . "    --radius: {$radius};\n"
        . "}\n"
        . "body { background-color: var(--color-background); color: var(--color-text); font-family: var(--font-family); }\n"
        . "a { color: {$link}; }\n"
        . "a:hover { color: {$linkHover}; }\n"
        . ".container, .page-section { background: var(--color-background); color: var(--color-text); }\n"
        . ".site-header { background-color: var(--color-header-background); }\n"
        . "button, .btn-primary { background-color: var(--color-primary); color: {$buttonText}; border: none; border-radius: var(--radius); }\n"
        . "button:hover, .btn-primary:hover { background-color: {$hover}; color: {$buttonText}; }\n"
        . ".page-section, .settings-card, .stat-card { background: {$box}; border-color: var(--color-border); }\n"
        . ".settings-card { background: var(--color-secondary); }\n"
        . ".form-group input, .form-group textarea, .form-group select, .settings-row input, .settings-row select, .settings-row textarea {\n"
        . "    background: {$box}; color: var(--color-text); border-color: var(--color-border); border-radius: var(--radius);\n"
        . "}\n"
        . ".stat-value, .profile-stat-value { color: {$stat}; }\n"
        . ".sidebar-nav a { color: var(--color-text); }\n"
        . ".sidebar-nav a.active, .sidebar-nav a:hover { background: var(--color-secondary); color: {$stat}; }\n"
        . ".file-row, .file-row-dashboard, .file-row-user-management, .file-row-file-management { border-bottom-color: var(--color-border); }\n"
        . ".welcome-banner { background: var(--color-secondary); border-left-color: var(--color-accent); }\n";
    $extra = datadock_sanitize_custom_css($extra);
    if ($extra !== '') {
        $css .= "\n/* Custom rules */\n" . $extra . "\n";
    }
    return $css;
}

function datadock_sanitize_custom_css(string $css): string {
    $css = str_replace("\0", '', $css);
    if (strlen($css) > 50000) {
        $css = substr($css, 0, 50000);
    }
    $css = preg_replace('/<\/?[a-zA-Z][^>]*>/', '', $css) ?? $css;
    $css = preg_replace('/expression\s*\(/i', 'blocked(', $css) ?? $css;
    $css = preg_replace('/javascript\s*:/i', 'blocked:', $css) ?? $css;
    $css = preg_replace('/@import\b/i', '/* import removed */', $css) ?? $css;
    $css = preg_replace('/-moz-binding\s*:/i', 'blocked:', $css) ?? $css;
    $css = preg_replace('/behavior\s*:/i', 'blocked:', $css) ?? $css;
    return trim($css);
}

/**
 * @return array<string, array<string, mixed>>
 */
function datadock_custom_theme_manifest(): array {
    $path = datadock_theme_custom_dir() . '/manifest.json';
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * @param array<string, array<string, mixed>> $manifest
 */
function datadock_write_custom_theme_manifest(array $manifest): bool {
    $dir = datadock_theme_custom_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return false;
    }
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return false;
    }
    return file_put_contents($dir . '/manifest.json', $json . "\n") !== false;
}

/**
 * @param array<string, string> $tokens
 * @return array{ok:bool,error:string,slug:string}
 */
function datadock_save_custom_theme(string $name, array $tokens, string $extra, string $existingSlug = ''): array {
    $name = trim($name);
    if ($name === '' || strlen($name) > 60) {
        return ['ok' => false, 'error' => 'Give the theme a name up to 60 characters.', 'slug' => ''];
    }
    $builtin = array_merge(['light', 'dark'], array_keys(datadock_builtin_theme_tokens()));
    $slug = $existingSlug !== '' ? $existingSlug : datadock_theme_slug_from_name($name);
    if (!datadock_theme_slug_ok($slug) || in_array($slug, $builtin, true)) {
        return ['ok' => false, 'error' => 'That name is reserved or cannot be used as a theme id.', 'slug' => ''];
    }
    $manifest = datadock_custom_theme_manifest();
    if ($existingSlug === '' && isset($manifest[$slug])) {
        return ['ok' => false, 'error' => 'A custom theme with that name already exists. Open it to replace it.', 'slug' => ''];
    }
    $css = datadock_render_theme_css($tokens, $extra);
    $dir = datadock_theme_custom_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return ['ok' => false, 'error' => 'Could not create the themes folder.', 'slug' => ''];
    }
    if (file_put_contents($dir . '/' . $slug . '.css', $css) === false) {
        return ['ok' => false, 'error' => 'Could not write the theme file.', 'slug' => ''];
    }
    $manifest[$slug] = [
        'name' => $name,
        'tokens' => $tokens,
        'extra' => datadock_sanitize_custom_css($extra),
    ];
    if (!datadock_write_custom_theme_manifest($manifest)) {
        return ['ok' => false, 'error' => 'The stylesheet was written, but the theme list could not be saved.', 'slug' => $slug];
    }
    return ['ok' => true, 'error' => '', 'slug' => $slug];
}

function datadock_delete_custom_theme(string $slug): bool {
    if (!datadock_theme_slug_ok($slug)) {
        return false;
    }
    $manifest = datadock_custom_theme_manifest();
    if (!isset($manifest[$slug])) {
        return false;
    }
    unset($manifest[$slug]);
    @unlink(datadock_theme_custom_dir() . '/' . $slug . '.css');
    return datadock_write_custom_theme_manifest($manifest);
}
