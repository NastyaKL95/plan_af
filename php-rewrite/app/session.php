<?php

/**
 * Параметры cookie сессии до session_start().
 * В app → session_cookie_secure: true на продакшене за HTTPS;
 * session_cookie_samesite: Lax | Strict | None (None только вместе с secure);
 * session_cookie_domain: необязательно, строка хоста;
 * session_cookie_lifetime: секунд, 0 — до закрытия браузера.
 */
function iro_session_start_from_config(array $config): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $app = $config['app'] ?? [];
    $secure = !empty($app['session_cookie_secure']);
    if (!$secure) {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            $secure = true;
        }
    }

    $sameRaw = strtoupper(trim((string) ($app['session_cookie_samesite'] ?? 'Lax')));
    $sameSite = match ($sameRaw) {
        'STRICT' => 'Strict',
        'NONE' => 'None',
        default => 'Lax',
    };
    if ($sameSite === 'None' && !$secure) {
        $sameSite = 'Lax';
    }

    $domain = trim((string) ($app['session_cookie_domain'] ?? ''));
    $lifetime = (int) ($app['session_cookie_lifetime'] ?? 0);

    $params = [
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => $sameSite,
    ];
    if ($domain !== '') {
        $params['domain'] = $domain;
    }

    session_set_cookie_params($params);
    session_start();
}
