<?php

declare(strict_types=1);

/**
 * Простое файловое логирование (JSON-строки по одной на событие).
 * Каталог: storage/logs/ относительно корня проекта (см. app.log_dir в config).
 */

function iro_log_enabled(array $config): bool
{
    $app = $config['app'] ?? [];

    return !empty($app['log_enabled']);
}

function iro_log_directory(array $config): string
{
    $app = $config['app'] ?? [];
    $rel = trim(str_replace(['\\', '..'], ['/', ''], (string) ($app['log_dir'] ?? 'storage/logs')), '/');
    if ($rel === '') {
        $rel = 'storage/logs';
    }

    return env_path($rel);
}

/**
 * @param array<string, mixed> $context
 */
function iro_log_line(string $level, string $message, array $context = []): void
{
    $config = $GLOBALS['config'] ?? null;
    if (!is_array($config) || !iro_log_enabled($config)) {
        return;
    }

    $dir = iro_log_directory($config);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return;
    }

    $file = $dir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
    $row = [
        'ts' => gmdate('c'),
        'level' => $level,
        'msg' => $message,
        'ctx' => $context,
    ];
    $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        $json = '{"ts":"' . gmdate('c') . '","level":"ERROR","msg":"json_encode failed"}';
    }
    $line = $json . "\n";

    $fp = @fopen($file, 'ab');
    if ($fp === false) {
        return;
    }
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, $line);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function iro_register_shutdown_log_fatal(): void
{
    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if ($err === null) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (!in_array($err['type'], $fatalTypes, true)) {
            return;
        }
        $config = $GLOBALS['config'] ?? null;
        if (!is_array($config) || !iro_log_enabled($config)) {
            return;
        }
        iro_log_line('FATAL', (string) ($err['message'] ?? ''), [
            'file' => (string) ($err['file'] ?? ''),
            'line' => (int) ($err['line'] ?? 0),
        ]);
    });
}
