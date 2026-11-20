<?php
declare(strict_types=1);

require_once __DIR__ . '/app_log.php';
require_once __DIR__ . '/login_rate_limit.php';

const AUDIT_FUND_REGISTER_RATE_WINDOW_SEC = 900;
const AUDIT_FUND_REGISTER_RATE_MAX_ATTEMPTS = 8;
const AUDIT_FUND_REGISTER_RATE_LOCKOUT_SEC = 900;

function audit_fund_register_rate_storage_dir(): string
{
    return dirname(__DIR__) . '/storage/register_rate_limit';
}

function audit_fund_register_rate_key(): string
{
    return hash('sha256', 'register:' . audit_fund_login_rate_client_ip());
}

/**
 * @return array{attempts:list<int>,locked_until:int}
 */
function audit_fund_register_rate_parse(string $raw): array
{
    if ($raw === '') {
        return ['attempts' => [], 'locked_until' => 0];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['attempts' => [], 'locked_until' => 0];
    }
    $attempts = [];
    if (isset($data['attempts']) && is_array($data['attempts'])) {
        foreach ($data['attempts'] as $ts) {
            if (is_int($ts) || (is_string($ts) && ctype_digit($ts))) {
                $attempts[] = (int) $ts;
            }
        }
    }
    $locked = isset($data['locked_until']) ? (int) $data['locked_until'] : 0;

    return ['attempts' => $attempts, 'locked_until' => $locked];
}

/**
 * @param array{attempts:list<int>,locked_until:int} $state
 */
function audit_fund_register_rate_normalize_state(array $state, int $now): array
{
    if ($state['locked_until'] > 0 && $now >= $state['locked_until']) {
        $state['locked_until'] = 0;
        $state['attempts'] = [];
    }
    $cutoff = $now - AUDIT_FUND_REGISTER_RATE_WINDOW_SEC;
    $state['attempts'] = array_values(array_filter(
        $state['attempts'],
        static fn (int $ts): bool => $ts >= $cutoff
    ));

    return $state;
}

/**
 * @param callable(array{attempts:list<int>,locked_until:int}, int):array{attempts:list<int>,locked_until:int} $mutate
 * @return array{0:array{attempts:list<int>,locked_until:int},1:bool}
 */
function audit_fund_register_rate_locked_mutate(callable $mutate): array
{
    $dir = audit_fund_register_rate_storage_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $path = $dir . '/' . audit_fund_register_rate_key() . '.json';
    $fh = @fopen($path, 'c+');
    $now = time();
    if ($fh === false) {
        $state = $mutate(['attempts' => [], 'locked_until' => 0], $now);

        return [$state, $state['locked_until'] > $now];
    }
    try {
        if (!flock($fh, LOCK_EX)) {
            $state = $mutate(['attempts' => [], 'locked_until' => 0], $now);

            return [$state, $state['locked_until'] > $now];
        }
        rewind($fh);
        $raw = stream_get_contents($fh) ?: '';
        $state = audit_fund_register_rate_normalize_state(audit_fund_register_rate_parse($raw), $now);
        $state = $mutate($state, $now);
        $locked = $state['locked_until'] > $now;
        $json = json_encode($state, JSON_THROW_ON_ERROR);
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, $json);
        fflush($fh);

        return [$state, $locked];
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'register_rate_limit');
        $state = $mutate(['attempts' => [], 'locked_until' => 0], $now);

        return [$state, $state['locked_until'] > $now];
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

function audit_fund_register_rate_is_locked(): bool
{
    [, $locked] = audit_fund_register_rate_locked_mutate(
        static function (array $state, int $now): array {
            return audit_fund_register_rate_normalize_state($state, $now);
        }
    );

    return $locked;
}

function audit_fund_register_rate_register_failure(): void
{
    audit_fund_register_rate_locked_mutate(
        static function (array $state, int $now): array {
            $state = audit_fund_register_rate_normalize_state($state, $now);
            $state['attempts'][] = $now;
            if (count($state['attempts']) >= AUDIT_FUND_REGISTER_RATE_MAX_ATTEMPTS) {
                $state['locked_until'] = $now + AUDIT_FUND_REGISTER_RATE_LOCKOUT_SEC;
                $state['attempts'] = [];
            }

            return $state;
        }
    );
}

function audit_fund_register_rate_clear_success(): void
{
    $path = audit_fund_register_rate_storage_dir() . '/' . audit_fund_register_rate_key() . '.json';
    if (is_file($path)) {
        @unlink($path);
    }
}
