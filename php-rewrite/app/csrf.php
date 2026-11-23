<?php

declare(strict_types=1);

/** Токен CSRF для POST-форм (сессионный). */
function iro_csrf_token(): string
{
    if (
        empty($_SESSION['iro_csrf_token'])
        || !is_string($_SESSION['iro_csrf_token'])
        || strlen($_SESSION['iro_csrf_token']) < 32
    ) {
        $_SESSION['iro_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['iro_csrf_token'];
}

/** HTML скрытого поля для встраивания в формы. */
function iro_csrf_field_html(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(iro_csrf_token()) . '">';
}

/**
 * Обязательная проверка CSRF для изменяющего состояние POST.
 * При ошибке — flash и редирект на безопасный относительный URL.
 */
function iro_csrf_verify_post(): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        return;
    }

    $sent = trim((string) ($_POST['csrf_token'] ?? ''));
    $sess = trim((string) ($_SESSION['iro_csrf_token'] ?? ''));
    if ($sess === '' || !hash_equals($sess, $sent)) {
        unset($_SESSION['iro_csrf_token']);
        flash_set('danger', 'Запрос отклонён (истекла сессия или недействительная форма). Обновите страницу и повторите действие.');
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $path = '';
        if ($ref !== '') {
            $parts = parse_url($ref);
            $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
            $q = is_array($parts) ? (string) ($parts['query'] ?? '') : '';
            if ($path !== '') {
                $safe = $path . ($q !== '' ? '?' . $q : '');
                if (str_starts_with($safe, '/') && !str_starts_with($safe, '//')) {
                    redirect($safe);
                    exit;
                }
            }
        }
        redirect('/');
        exit;
    }
}
