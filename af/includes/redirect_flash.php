<?php
declare(strict_types=1);

/**
 * Одноразовые уведомления после POST: хранятся в сессии, редирект на чистый URL.
 */

function audit_fund_flash_page_key_from_url(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return 'index.php';
    }
    $base = basename($path);

    return $base !== '' ? $base : 'index.php';
}

/** @param array<string, bool|int|string|float> $params */
function audit_fund_flash_set(string $pageKey, array $params): void
{
    audit_fund_session_start();
    if (!isset($_SESSION['audit_fund_page_flash']) || !is_array($_SESSION['audit_fund_page_flash'])) {
        $_SESSION['audit_fund_page_flash'] = [];
    }
    $_SESSION['audit_fund_page_flash'][$pageKey] = $params;
}

/**
 * @return array<string, bool|int|string|float>
 */
function audit_fund_flash_take(string $pageKey): array
{
    audit_fund_session_start();
    if (!isset($_SESSION['audit_fund_page_flash'][$pageKey]) || !is_array($_SESSION['audit_fund_page_flash'][$pageKey])) {
        return [];
    }
    $out = $_SESSION['audit_fund_page_flash'][$pageKey];
    unset($_SESSION['audit_fund_page_flash'][$pageKey]);

    return is_array($out) ? $out : [];
}

/** @param array<string, bool|int|string|float> $flash */
function audit_fund_redirect_with_flash(string $url, array $flash = []): void
{
    if ($flash !== []) {
        audit_fund_flash_set(audit_fund_flash_page_key_from_url($url), $flash);
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Добавить week_start из POST (скрытое поле на странице расписания), чтобы вернуться на ту же неделю.
 *
 * @param array<string, bool|int|string|float> $flash
 * @return array<string, bool|int|string|float>
 */
function audit_fund_schedule_flash_merge_week(array $flash): array
{
    if (isset($_POST['week_start']) && !is_array($_POST['week_start'])) {
        $w = trim((string) $_POST['week_start']);
        if ($w !== '') {
            $flash['week_start'] = $w;
        }
    }

    return $flash;
}
