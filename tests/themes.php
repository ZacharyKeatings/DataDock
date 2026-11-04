<?php

require_once dirname(__DIR__) . '/includes/themes.php';

$_SERVER['SCRIPT_NAME'] = '/index.php';

expect_true('midnight is a known theme', datadock_theme_is_known('midnight'));
expect_false('blank theme name is unknown', datadock_theme_is_known(''));
expect_same('theme slug from a label', datadock_theme_slug_from_name('Ocean Night!'), 'ocean-night');
expect_false('theme slug rejects spaces', datadock_theme_slug_ok('ocean night'));
expect_same('short hex color expands', datadock_hex_color('#abc', '#000000'), '#aabbcc');
expect_same('invalid hex uses the fallback', datadock_hex_color('blue', '#112233'), '#112233');
expect_same('hex channel shift stays in range', datadock_shift_hex('#000000', 16), '#101010');

$css = datadock_render_theme_css([
    'scheme' => 'dark',
    'primary' => '#4db8c0',
    'background' => '#1e2426',
]);
expect_contains('theme css includes the primary color', $css, '#4db8c0');
expect_contains('theme css sets the color scheme', $css, 'color-scheme: dark');
$cleaned = datadock_sanitize_custom_css("<style>@import url('x');\nbody{background:expression(alert(1));}</style>");
expect_false('custom css drops import', strpos($cleaned, '@import') !== false);
expect_false('custom css drops expression', stripos($cleaned, 'expression') !== false);
expect_false('custom css drops tags', strpos($cleaned, '<style>') !== false);

expect_same('site theme is used for a guest', datadock_resolve_theme(['theme' => 'ocean']), 'ocean');
expect_same('unknown site theme falls back to light', datadock_resolve_theme(['theme' => 'missing']), 'light');
expect_contains('catalog links the ocean stylesheet', datadock_theme_stylesheet_url('ocean'), 'ocean.css');
