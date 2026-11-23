<?php
declare(strict_types=1);

/**
 * Минимальная автозагрузка при наличии vendor/autoload.php (composer install).
 * Дублирует подключения, если Composer не используется — см. auth.php.
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security_headers.php';
