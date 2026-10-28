<?php
/**
 * Page query helpers for long lists.
 *
 * @return array{param:string,page:int,per_page:int,offset:int}
 */
function datadock_page_request(string $param = 'p', int $perPage = 25): array {
    $perPage = max(1, min(100, $perPage));
    $page = isset($_GET[$param]) ? (int) $_GET[$param] : 1;
    if ($page < 1) {
        $page = 1;
    }
    return [
        'param' => $param,
        'page' => $page,
        'per_page' => $perPage,
        'offset' => ($page - 1) * $perPage,
    ];
}

/**
 * @param array{param:string,page:int,per_page:int,offset:int} $pager
 * @return array{param:string,page:int,per_page:int,offset:int,pages:int,total:int}
 */
function datadock_page_clamp(array $pager, int $total): array {
    if ($total < 0) {
        $total = 0;
    }
    $pages = max(1, (int) ceil($total / $pager['per_page']));
    if ($pager['page'] > $pages) {
        $pager['page'] = $pages;
        $pager['offset'] = ($pages - 1) * $pager['per_page'];
    }
    $pager['pages'] = $pages;
    $pager['total'] = $total;
    return $pager;
}

/**
 * @param array{param?:string,page?:int,pages?:int} $pager
 */
function datadock_render_pager(array $pager): void {
    $pages = (int) ($pager['pages'] ?? 1);
    if ($pages <= 1) {
        return;
    }
    $param = (string) ($pager['param'] ?? 'p');
    $current = (int) ($pager['page'] ?? 1);
    $query = $_GET;
    unset($query[$param]);
    echo '<nav class="pager file-mgmt-filter" aria-label="Pages">';
    $start = max(1, $current - 3);
    $end = min($pages, $current + 3);
    if ($current > 1) {
        $query[$param] = $current - 1;
        echo '<a class="btn btn-quiet btn-small" href="?' . htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8') . '">Previous</a>';
    }
    if ($start > 1) {
        $query[$param] = 1;
        echo '<a class="btn btn-quiet btn-small" href="?' . htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8') . '">1</a>';
        if ($start > 2) {
            echo '<span class="pager-gap" aria-hidden="true">…</span>';
        }
    }
    for ($p = $start; $p <= $end; $p++) {
        if ($p === $current) {
            echo '<span class="btn btn-small btn-primary" aria-current="page">' . $p . '</span>';
        } else {
            $query[$param] = $p;
            echo '<a class="btn btn-quiet btn-small" href="?' . htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8') . '">' . $p . '</a>';
        }
    }
    if ($end < $pages) {
        if ($end < $pages - 1) {
            echo '<span class="pager-gap" aria-hidden="true">…</span>';
        }
        $query[$param] = $pages;
        echo '<a class="btn btn-quiet btn-small" href="?' . htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8') . '">' . $pages . '</a>';
    }
    if ($current < $pages) {
        $query[$param] = $current + 1;
        echo '<a class="btn btn-quiet btn-small" href="?' . htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8') . '">Next</a>';
    }
    echo '</nav>';
}
