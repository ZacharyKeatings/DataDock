<?php

expect_same('sanitize trims and escapes', sanitize_data(' <b>hi</b> '), '&lt;b&gt;hi&lt;/b&gt;');
expect_same('plain flash strips a leading symbol', datadock_plain_flash("⚠ saved"), 'saved');
expect_same('plain flash keeps a normal sentence', datadock_plain_flash('Saved'), 'Saved');

expect_same('guest profile is plain text', user_profile_link('Guest'), 'Guest');
expect_same('empty profile is Guest', user_profile_link(''), 'Guest');
expect_contains('username becomes a profile link', user_profile_link('Ada'), 'user.php?username=Ada');
expect_contains('profile link escapes the label', user_profile_link('A<da'), 'A&lt;da');

$_SERVER['SCRIPT_NAME'] = '/datadock/index.php';
expect_same('script url keeps the install directory', app_script_url('upload.php'), '/datadock/upload.php');
$_SERVER['SCRIPT_NAME'] = '/index.php';
expect_same('script url at the web root', app_script_url('upload.php'), '/upload.php');

expect_contains('datetime display splits date and time', format_datetime_display('2025-02-18 14:32:05'), 'Feb 18, 2025');
expect_contains('datetime display includes the clock', format_datetime_display('2025-02-18 14:32:05'), '2:32 PM');
expect_same('blank datetime is empty', format_datetime_display(''), '');
expect_same('unparseable datetime is empty', format_datetime_display('not-a-date'), '');

expect_contains('markdown escapes raw html', basic_markdown('<script>alert(1)</script>'), '&lt;script&gt;');
expect_contains('markdown bold', basic_markdown('**bold**'), '<strong>bold</strong>');
expect_contains('markdown blocks javascript links', basic_markdown('[x](javascript:alert(1))'), 'href="#"');
expect_contains(
    'markdown resolves a relative link',
    basic_markdown('[notes](docs/readme.md)', 'https://example.com/app/index.php'),
    'https://example.com/app/docs/readme.md'
);

expect_same('javascript url is dropped', resolve_markdown_url('javascript:alert(1)'), '#');
expect_same('https url is kept', resolve_markdown_url('https://example.com/a'), 'https://example.com/a');
expect_same('root-relative url uses the base host', resolve_markdown_url('/files/1', 'https://example.com/app/index.php'), 'https://example.com/files/1');
