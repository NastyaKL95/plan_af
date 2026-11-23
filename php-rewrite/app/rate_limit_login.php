<?php

declare(strict_types=1);

/**
 * Ограничение частоты неудачных попыток входа по IP (файлы в storage/cache).
 * Параметры: app.login_rate_limit_max (по умолчанию 15), app.login_rate_limit_window (сек, по умолчанию 900).
 */

function iro_client_ip_for_rate_limit(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip !== '') {
        return $ip;
    }

    return '0.0.0.0';
}

function iro_login_rate_limit_max(array $config): int
{
    $app = $config['app'] ?? [];
    $v = (int) ($app['login_rate_limit_max'] ?? 15);

    return max(5, min(60, $v));
}

function iro_login_rate_limit_window_seconds(array $config): int
{
    $app = $config['app'] ?? [];
    $v = (int) ($app['login_rate_limit_window'] ?? 900);

    return max(120, min(7200, $v));
}

function iro_login_rate_limit_cache_dir(array $config): string
{
    $app = $config['app'] ?? [];
    $rel = trim(str_replace(['\\', '..'], ['/', ''], (string) ($app['login_rate_limit_cache_dir'] ?? 'storage/cache')), '/');
    if ($rel === '') {
        $rel = 'storage/cache';
    }

    return env_path($rel);
}

function iro_login_rate_limit_file(array $config): string
{
    $ip = iro_client_ip_for_rate_limit();
    $hash = hash('sha256', $ip . '|login', false);
    $dir = iro_login_rate_limit_cache_dir($config);

    return $dir . DIRECTORY_SEPARATOR . 'login_rl_' . $hash . '.json';
}

/**
 * @return array{window_start: int, count: int}
 */
function iro_login_rate_limit_read(string $path): array
{
    if (!is_file($path)) {
        return ['window_start' => 0, 'count' => 0];
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return ['window_start' => 0, 'count' => 0];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['window_start' => 0, 'count' => 0];
    }

    return [
        'window_start' => (int) ($data['window_start'] ?? 0),
        'count' => (int) ($data['count'] ?? 0),
    ];
}

function iro_login_rate_limit_write(string $path, int $windowStart, int $count): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return;
    }
    $json = json_encode(['window_start' => $windowStart, 'count' => $count]);
    if ($json === false) {
        return;
    }
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $json) !== false) {
        @rename($tmp, $path);
    } else {
        @unlink($tmp);
    }
}

/** null — можно пробовать вход; строка — сообщение для пользователя (уже заблокировано). */
function iro_login_rate_limit_check(array $config): ?string
{
    if (array_key_exists('login_rate_limit_enabled', $config['app'] ?? []) && empty($config['app']['login_rate_limit_enabled'])) {
        return null;
    }

    $path = iro_login_rate_limit_file($config);
    $now = time();
    $window = iro_login_rate_limit_window_seconds($config);
    $max = iro_login_rate_limit_max($config);
    $state = iro_login_rate_limit_read($path);
    $start = $state['window_start'];
    $count = $state['count'];
    if ($start <= 0 || ($now - $start) > $window) {
        return null;
    }
    if ($count >= $max) {
        $wait = $window - ($now - $start);
        $mins = max(1, (int) ceil($wait / 60));

        return 'Слишком много неудачных попыток входа. Повторите через ' . $mins . ' мин.';
    }

    return null;
}

function iro_login_rate_limit_hit_failed(array $config): void
{
    if (array_key_exists('login_rate_limit_enabled', $config['app'] ?? []) && empty($config['app']['login_rate_limit_enabled'])) {
        return;
    }

    $path = iro_login_rate_limit_file($config);
    $now = time();
    $window = iro_login_rate_limit_window_seconds($config);
    $max = iro_login_rate_limit_max($config);
    $state = iro_login_rate_limit_read($path);
    $start = $state['window_start'];
    $count = $state['count'];
    if ($start <= 0 || ($now - $start) > $window) {
        $start = $now;
        $count = 0;
    }
    $count++;
    iro_login_rate_limit_write($path, $start, $count);
    if ($count >= $max) {
        iro_log_line('WARNING', 'login rate limit reached', ['ip' => iro_client_ip_for_rate_limit()]);
    }
}

function iro_login_rate_limit_clear(array $config): void
{
    $path = iro_login_rate_limit_file($config);
    if (is_file($path)) {
        @unlink($path);
    }
}
