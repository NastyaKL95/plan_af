<?php
declare(strict_types=1);

require_once __DIR__ . '/install_lib.php';

/**
 * Редирект на мастер установки, если нет config/database.local.php (сценарий «как WordPress»).
 * CLI и запросы к /install/ не трогаем.
 */
function audit_fund_install_redirect_if_needed(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    if (!audit_fund_install_wizard_enabled()) {
        return;
    }

    $root = dirname(__DIR__);
    if (is_readable($root . '/config/database.local.php')) {
        return;
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $pathOnly = (string) (parse_url($uri, PHP_URL_PATH) ?? '');
    if (str_contains($pathOnly, '/install/') || str_ends_with(rtrim($pathOnly, '/'), '/install')) {
        return;
    }

    $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    if (str_contains($script, DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR)) {
        return;
    }

    $scriptName = audit_fund_install_normalize_script_web_path($_SERVER['SCRIPT_NAME'] ?? null);
    if ($scriptName === '') {
        $scriptName = '/index.php';
    }
    $dir = str_replace('\\', '/', dirname($scriptName));
    if ($dir === '/' || $dir === '.' || $dir === '') {
        $to = '/install/';
    } else {
        $to = rtrim($dir, '/') . '/install/';
    }
    header('Location: ' . $to, true, 302);
    exit;
}

/** Базовый путь приложения в URL (с ведущим и завершающим /). */
function audit_fund_install_public_base_path(): string
{
    $scriptName = audit_fund_install_normalize_script_web_path($_SERVER['SCRIPT_NAME'] ?? null);
    if ($scriptName === '') {
        $scriptName = '/index.php';
    }
    $dir = dirname($scriptName);
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return '/';
    }

    return rtrim(str_replace('\\', '/', $dir), '/') . '/';
}
