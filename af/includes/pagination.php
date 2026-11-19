<?php
declare(strict_types=1);

/**
 * Номер страницы из GET (1 … $maxPage).
 */
function audit_fund_pagination_page(?string $raw, int $maxPage = 100000): int
{
    if ($raw === null || $raw === '' || !ctype_digit($raw)) {
        return 1;
    }
    $p = (int) $raw;
    if ($p < 1) {
        return 1;
    }

    return min($maxPage, $p);
}

/**
 * Размер страницы из GET с ограничением.
 */
function audit_fund_pagination_per_page(?string $raw, int $default = 25, int $max = 100): int
{
    if ($raw === null || $raw === '' || !ctype_digit($raw)) {
        return $default;
    }
    $n = (int) $raw;
    if ($n < 5) {
        return 5;
    }

    return min($max, $n);
}

function audit_fund_pagination_total_pages(int $total, int $perPage): int
{
    if ($perPage < 1) {
        return 1;
    }
    if ($total <= 0) {
        return 1;
    }

    return (int) max(1, (int) ceil($total / $perPage));
}

function audit_fund_pagination_clamp_page(int $page, int $totalPages): int
{
    if ($page < 1) {
        return 1;
    }
    if ($totalPages < 1) {
        return 1;
    }

    return min($page, $totalPages);
}

/**
 * @param array<string, string> $queryParams сохраняемые параметры (без $pageKey)
 */
function audit_fund_pagination_href(string $scriptPath, array $queryParams, string $pageKey, int $page): string
{
    $q = $queryParams;
    if ($page <= 1) {
        unset($q[$pageKey]);
    } else {
        $q[$pageKey] = (string) $page;
    }
    $qs = http_build_query($q);

    return $scriptPath . ($qs !== '' ? '?' . $qs : '');
}

/**
 * @param array<string, string> $queryParams
 */
function audit_fund_pagination_render(
    string $scriptPath,
    array $queryParams,
    string $pageKey,
    int $currentPage,
    int $totalPages,
    int $totalItems,
    int $perPage,
    string $itemsLabel = 'записей'
): void {
    if ($totalItems === 0) {
        return;
    }
    $from = ($currentPage - 1) * $perPage + 1;
    $to = min($totalItems, $currentPage * $perPage);
    $prev = $currentPage > 1 ? $currentPage - 1 : null;
    $next = $currentPage < $totalPages ? $currentPage + 1 : null;

    echo '<nav class="pagination" aria-label="Постраничная навигация">';
    echo '<span class="pagination__info">';
    echo htmlspecialchars(
        $from . '–' . $to . ' из ' . $totalItems . ' ' . $itemsLabel,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
    if ($totalPages > 1) {
        echo ' · страница ' . $currentPage . ' из ' . $totalPages;
    }
    echo '</span>';
    if ($totalPages <= 1) {
        echo '</nav>';

        return;
    }
    echo '<div class="pagination__links">';
    if ($prev !== null) {
        $h = htmlspecialchars(
            audit_fund_pagination_href($scriptPath, $queryParams, $pageKey, $prev),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        echo '<a class="btn btn--ghost btn--sm" href="' . $h . '">Назад</a>';
    }
    $window = 2;
    $start = max(1, $currentPage - $window);
    $end = min($totalPages, $currentPage + $window);
    for ($p = $start; $p <= $end; $p++) {
        $h = htmlspecialchars(
            audit_fund_pagination_href($scriptPath, $queryParams, $pageKey, $p),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        if ($p === $currentPage) {
            echo '<span class="pagination__current" aria-current="page">' . $p . '</span>';
        } else {
            echo '<a class="btn btn--ghost btn--sm" href="' . $h . '">' . $p . '</a>';
        }
    }
    if ($next !== null) {
        $h = htmlspecialchars(
            audit_fund_pagination_href($scriptPath, $queryParams, $pageKey, $next),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        echo '<a class="btn btn--ghost btn--sm" href="' . $h . '">Вперёд</a>';
    }
    echo '</div>';
    echo '</nav>';
}
