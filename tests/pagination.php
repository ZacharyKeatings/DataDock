<?php

$_GET = [];
expect_same('default page request', datadock_page_request(), [
    'param' => 'p',
    'page' => 1,
    'per_page' => 25,
    'offset' => 0,
]);

$_GET = ['p' => '3'];
expect_same('page request reads the query', datadock_page_request('p', 10), [
    'param' => 'p',
    'page' => 3,
    'per_page' => 10,
    'offset' => 20,
]);

$_GET = ['p' => '0'];
$page = datadock_page_request('p', 500);
expect_same('page below 1 becomes 1', $page['page'], 1);
expect_same('per page is capped at 100', $page['per_page'], 100);

$clamped = datadock_page_clamp([
    'param' => 'p',
    'page' => 9,
    'per_page' => 25,
    'offset' => 200,
], 10);
expect_same('page past the end is pulled back', $clamped['page'], 1);
expect_same('clamped offset matches the last page', $clamped['offset'], 0);
expect_same('total is stored', $clamped['total'], 10);

ob_start();
datadock_render_pager(['pages' => 1, 'page' => 1, 'param' => 'p']);
expect_same('single page renders nothing', ob_get_clean(), '');

$_GET = ['q' => 'notes'];
ob_start();
datadock_render_pager(['pages' => 5, 'page' => 2, 'param' => 'p']);
$html = ob_get_clean();
expect_contains('pager marks the current page', $html, 'aria-current="page"');
expect_contains('pager keeps other query parameters', $html, 'q=notes');
expect_contains('pager offers the next page', $html, 'p=3');
$_GET = [];
