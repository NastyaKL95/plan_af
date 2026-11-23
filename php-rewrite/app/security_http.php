<?php

declare(strict_types=1);

/**
 * HTTP-заголовки безопасности для HTML-ответов (вызывать до любого вывода).
 * Отключение: в config app.security_headers_enabled = false.
 * Свой CSP: app.security_csp (полная строка политики).
 */
function iro_security_headers_send(array $config): void
{
    if (headers_sent()) {
        return;
    }

    $app = $config['app'] ?? [];
    if (array_key_exists('security_headers_enabled', $app) && empty($app['security_headers_enabled'])) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    header('X-Frame-Options: SAMEORIGIN');

    $csp = trim((string) ($app['security_csp'] ?? ''));
    if ($csp === '') {
        $csp = implode(' ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "img-src 'self' data: https:",
            "font-src 'self' https://fonts.gstatic.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "connect-src 'self'",
            "upgrade-insecure-requests",
        ]);
    }
    header('Content-Security-Policy: ' . $csp);
}
