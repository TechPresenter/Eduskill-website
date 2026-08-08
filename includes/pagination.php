<?php
/**
 * =============================================================================
 *  Pagination
 * =============================================================================
 *  paginate() runs a COUNT + a LIMITed fetch for a given query and returns both
 *  the page of items and everything needed to render pager links.
 * =============================================================================
 */

declare(strict_types=1);

/**
 * Paginate a SELECT query.
 *
 * @param string $sql       Full query WITHOUT a LIMIT clause.
 * @param array  $params    Bound parameters for the query.
 * @param int    $perPage   Items per page.
 * @param string $pageParam Query-string key holding the current page number.
 *
 * @return array {
 *   items:      array  the current page of rows
 *   total:      int    total matching rows
 *   per_page:   int
 *   current:    int    current page (1-based)
 *   last_page:  int
 *   from:       int    1-based index of first item on page
 *   to:         int
 *   has_prev:   bool
 *   has_next:   bool
 *   links:      string ready-to-echo HTML pager
 * }
 */
function paginate(string $sql, array $params = [], int $perPage = 12, string $pageParam = 'page'): array
{
    $perPage = max(1, $perPage);
    $current = max(1, (int) ($_GET[$pageParam] ?? 1));

    // Total count via a wrapping subquery (works for joins/group-by-free queries).
    $total = (int) db_value("SELECT COUNT(*) FROM ($sql) AS _pcount", $params);

    $lastPage = max(1, (int) ceil($total / $perPage));
    $current  = min($current, $lastPage);
    $offset   = ($current - 1) * $perPage;

    $items = db_all($sql . " LIMIT $perPage OFFSET $offset", $params);

    $meta = [
        'items'     => $items,
        'total'     => $total,
        'per_page'  => $perPage,
        'current'   => $current,
        'last_page' => $lastPage,
        'from'      => $total ? $offset + 1 : 0,
        'to'        => min($offset + $perPage, $total),
        'has_prev'  => $current > 1,
        'has_next'  => $current < $lastPage,
    ];
    $meta['links'] = render_pagination($meta, $pageParam);
    return $meta;
}

/**
 * Build the HTML pager, preserving all existing query-string parameters.
 */
function render_pagination(array $meta, string $pageParam = 'page'): string
{
    if ($meta['last_page'] <= 1) {
        return '';
    }

    $current  = $meta['current'];
    $last     = $meta['last_page'];
    $query    = $_GET;

    $link = static function (int $page) use ($query, $pageParam): string {
        $query[$pageParam] = $page;
        return '?' . http_build_query($query);
    };

    // Windowed page range.
    $start = max(1, $current - 2);
    $end   = min($last, $current + 2);

    $html  = '<nav class="pagination" aria-label="Pagination">';
    // Prev
    $html .= $meta['has_prev']
        ? '<a class="page-link" href="' . e($link($current - 1)) . '" rel="prev" aria-label="Previous">&laquo;</a>'
        : '<span class="page-link is-disabled" aria-hidden="true">&laquo;</span>';

    if ($start > 1) {
        $html .= '<a class="page-link" href="' . e($link(1)) . '">1</a>';
        if ($start > 2) {
            $html .= '<span class="page-ellipsis">…</span>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $html .= $i === $current
            ? '<span class="page-link is-active" aria-current="page">' . $i . '</span>'
            : '<a class="page-link" href="' . e($link($i)) . '">' . $i . '</a>';
    }
    if ($end < $last) {
        if ($end < $last - 1) {
            $html .= '<span class="page-ellipsis">…</span>';
        }
        $html .= '<a class="page-link" href="' . e($link($last)) . '">' . $last . '</a>';
    }
    // Next
    $html .= $meta['has_next']
        ? '<a class="page-link" href="' . e($link($current + 1)) . '" rel="next" aria-label="Next">&raquo;</a>'
        : '<span class="page-link is-disabled" aria-hidden="true">&raquo;</span>';

    $html .= '</nav>';
    return $html;
}
