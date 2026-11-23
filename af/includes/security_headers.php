<?php
declare(strict_types=1);

/**
 * Базовые заголовки безопасности для HTML-страниц приложения.
 * Вызывается из auth.php при каждом подключении.
 */
function audit_fund_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header(
        'Content-Security-Policy: default-src \'self\'; '
        . 'base-uri \'self\'; form-action \'self\'; '
        . 'script-src \'self\' \'unsafe-inline\'; '
        . 'style-src \'self\' \'unsafe-inline\'; '
        . 'img-src \'self\' data:; '
        . 'font-src \'self\' data:'
    );
}
