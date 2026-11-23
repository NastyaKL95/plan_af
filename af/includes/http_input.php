<?php
declare(strict_types=1);

/**
 * Чтение POST для «защиты от дурака»: значения-массивы не приводятся к int/string.
 */

function audit_fund_post_int(string $key): int
{
    if (!isset($_POST[$key]) || is_array($_POST[$key])) {
        return 0;
    }

    return (int) $_POST[$key];
}

function audit_fund_post_string(string $key): string
{
    if (!isset($_POST[$key]) || is_array($_POST[$key])) {
        return '';
    }

    return (string) $_POST[$key];
}

/**
 * Как audit_fund_post_string, но различает «поля нет» и «пустая строка».
 */
function audit_fund_post_string_key(string $key): ?string
{
    if (!array_key_exists($key, $_POST) || is_array($_POST[$key])) {
        return null;
    }

    return (string) $_POST[$key];
}

/**
 * Чтение GET: значения-массивы не приводятся к int/string.
 */
function audit_fund_get_int(string $key): int
{
    if (!isset($_GET[$key]) || is_array($_GET[$key])) {
        return 0;
    }

    return (int) $_GET[$key];
}

function audit_fund_get_string(string $key): string
{
    if (!isset($_GET[$key]) || is_array($_GET[$key])) {
        return '';
    }

    return (string) $_GET[$key];
}

function audit_fund_get_string_key(string $key): ?string
{
    if (!array_key_exists($key, $_GET) || is_array($_GET[$key])) {
        return null;
    }

    return (string) $_GET[$key];
}
