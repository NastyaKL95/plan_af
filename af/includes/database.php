<?php
declare(strict_types=1);

require_once __DIR__ . '/app_log.php';

/**
 * @return array<string, mixed>
 */
function audit_fund_db_config(): array
{
    $defaults = [
        // Для OpenServer порт берём из профиля mysqlport (в вашей среде открыт 3306).
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'audit_fund',
        'user' => 'root',
        'password' => 'root',
        'charset' => 'utf8mb4',
        /** Выкладка в прод: false после применения sql/*.sql — без CREATE/ALTER на каждом запросе */
        'runtime_migrations' => true,
        /**
         * Продакшен: скрыть вывод ошибок PHP в HTML (дублируется переменной окружения AUDIT_FUND_PRODUCTION=true).
         * Логирование через error_log сервера не отключается.
         */
        'production' => false,
        /** false — открытая регистрация отключена (только вход) */
        'registration_open' => true,
        /** Время жизни cookie сессии в секундах (0 — до закрытия браузера) */
        'session_cookie_lifetime' => 0,
        /** null — включать Secure только при HTTPS; true/false — принудительно */
        'session_cookie_secure' => null,
        /** Lax | Strict | None */
        'session_cookie_samesite' => 'Lax',
        /**
         * Яндекс ID. relay — через уже зарегистрированный callback Вектора (кабинет Яндекса менять не нужно).
         * direct — свой redirect URI, его нужно добавить в приложение OAuth: https://af.zabedu.ru/yandex_callback.php
         */
        'yandex_oauth_mode' => 'relay',
        'yandex_oauth_enabled' => true,
        'yandex_client_id' => 'e123475d08d848b8b3a8a89a797319a4',
        'yandex_client_secret' => '70cd9594f7f543ca9bac07fe54d8fbac',
        'yandex_redirect_uri' => 'https://af.zabedu.ru/yandex_callback.php',
        'yandex_scope' => 'login:email login:info',
        /** Вход через Яндекс только с этих доменов почты. */
        'yandex_allowed_domains' => ['iro75.ru'],
        /** Общий секрет с Вектором. Менять только одновременно на обоих сайтах. */
        'sso_enabled' => true,
        'sso_secret' => 'af5f7b16f09a100d8e3588020c3f3ed198a559afffb92c2b7a740df8fb5feea6',
        'sso_self_consume_url' => 'https://af.zabedu.ru/sso_consume.php',
        'sso_peer_check_url' => 'https://planiro75.ru/public/sso/check.php',
        'sso_peer_consume_url' => 'https://planiro75.ru/public/sso/consume.php',
        'sso_peer_logout_url' => 'https://planiro75.ru/public/sso/logout.php',
        'sso_peer_yandex_start_url' => 'https://planiro75.ru/public/auth/yandex_start.php',
        'sso_logout_return_url' => 'https://af.zabedu.ru/index.php',
        'sso_logout_accept_return' => 'https://planiro75.ru/public/login.php',
    ];
    $local = __DIR__ . '/../config/database.local.php';
    if (is_readable($local)) {
        /** @var array<string, mixed> $override */
        $override = require $local;
        return array_merge($defaults, $override);
    }

    return $defaults;
}

function audit_fund_runtime_migrations_enabled(): bool
{
    return (bool) audit_fund_db_config()['runtime_migrations'];
}

/**
 * Режим продакшена: из config (production) или из окружения AUDIT_FUND_PRODUCTION (=1 / true / yes).
 */
function audit_fund_is_production(): bool
{
    $env = getenv('AUDIT_FUND_PRODUCTION');
    if ($env !== false && $env !== '') {
        $v = strtolower(trim((string) $env));

        return in_array($v, ['1', 'true', 'yes'], true);
    }

    return (bool) audit_fund_db_config()['production'];
}

/**
 * Отключить вывод ошибок PHP в ответ (оставить в журнале сервера). Вызывать после загрузки database.php.
 */
function audit_fund_apply_production_php_settings(): void
{
    static $applied = false;
    if ($applied) {
        return;
    }
    $applied = true;
    if (!audit_fund_is_production()) {
        return;
    }
    @ini_set('display_errors', '0');
    @ini_set('display_startup_errors', '0');
}

function audit_fund_registration_open(): bool
{
    return (bool) audit_fund_db_config()['registration_open'];
}

/**
 * Параметры cookie PHP-сессии (HttpOnly, SameSite; Secure при HTTPS или из конфига).
 * Вызывать до session_start().
 */
function audit_fund_configure_session_cookie(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $c = audit_fund_db_config();
    $lifetime = isset($c['session_cookie_lifetime']) ? (int) $c['session_cookie_lifetime'] : 0;
    $secure = $c['session_cookie_secure'] ?? null;
    if ($secure === null) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    } else {
        $secure = (bool) $secure;
    }
    $samesite = isset($c['session_cookie_samesite']) ? (string) $c['session_cookie_samesite'] : 'Lax';
    if (!in_array($samesite, ['Lax', 'Strict', 'None'], true)) {
        $samesite = 'Lax';
    }

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => $samesite,
    ]);
}

function audit_fund_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = audit_fund_db_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $c['host'],
        (int) $c['port'],
        $c['database'],
        $c['charset']
    );

    try {
        $pdo = new PDO($dsn, $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'audit_fund_pdo');
        throw $e;
    }

    return $pdo;
}

/**
 * Имя advisory lock MySQL (≤64 символа) для сериализации операций с бронированием по аудитории и календарному дню.
 */
function audit_fund_booking_day_lock_name(int $roomId, string $dateYmd): string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
        $dateYmd = '0000-00-00';
    }
    $h = hash('sha256', 'audit_fund_booking_day' . "\0" . (string) $roomId . "\0" . $dateYmd);

    return 'af_' . substr($h, 0, 56);
}

/**
 * @return bool true, если блокировка получена (GET_LOCK = 1)
 */
function audit_fund_mysql_get_lock(PDO $pdo, string $lockName, int $timeoutSeconds = 15): bool
{
    if ($lockName === '') {
        return false;
    }
    if (strlen($lockName) > 64) {
        $lockName = substr($lockName, 0, 64);
    }
    $st = $pdo->prepare('SELECT GET_LOCK(?, ?)');
    $st->execute([$lockName, $timeoutSeconds]);
    $v = $st->fetchColumn();

    return $v === 1 || $v === '1';
}

function audit_fund_mysql_release_lock(PDO $pdo, string $lockName): void
{
    if ($lockName === '') {
        return;
    }
    if (strlen($lockName) > 64) {
        $lockName = substr($lockName, 0, 64);
    }
    try {
        $st = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $st->execute([$lockName]);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'mysql_release_lock');
    }
}

/**
 * Несколько блокировок в фиксированном порядке (меньше взаимных ожиданий при переносе между днями/аудиториями).
 *
 * @param list<array{0:int,1:string}> $roomDatePairs
 */
function audit_fund_mysql_acquire_room_date_locks(PDO $pdo, array $roomDatePairs, int $timeoutSeconds = 15): bool
{
    $uniq = [];
    foreach ($roomDatePairs as $pair) {
        $rid = (int) ($pair[0] ?? 0);
        $d = (string) ($pair[1] ?? '');
        if ($rid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            continue;
        }
        $uniq[$rid . "\n" . $d] = [$rid, $d];
    }
    $list = array_values($uniq);
    usort($list, static function (array $a, array $b): int {
        if ($a[0] !== $b[0]) {
            return $a[0] <=> $b[0];
        }

        return strcmp($a[1], $b[1]);
    });

    $acquiredNames = [];
    foreach ($list as $pair) {
        $name = audit_fund_booking_day_lock_name($pair[0], $pair[1]);
        if (!audit_fund_mysql_get_lock($pdo, $name, $timeoutSeconds)) {
            foreach (array_reverse($acquiredNames) as $n) {
                audit_fund_mysql_release_lock($pdo, $n);
            }

            return false;
        }
        $acquiredNames[] = $name;
    }

    return true;
}

/**
 * @param list<array{0:int,1:string}> $roomDatePairs те же пары, что передавали в audit_fund_mysql_acquire_room_date_locks
 */
function audit_fund_mysql_release_room_date_locks(PDO $pdo, array $roomDatePairs): void
{
    $uniq = [];
    foreach ($roomDatePairs as $pair) {
        $rid = (int) ($pair[0] ?? 0);
        $d = (string) ($pair[1] ?? '');
        if ($rid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            continue;
        }
        $uniq[$rid . "\n" . $d] = [$rid, $d];
    }
    $list = array_values($uniq);
    usort($list, static function (array $a, array $b): int {
        if ($a[0] !== $b[0]) {
            return $a[0] <=> $b[0];
        }

        return strcmp($a[1], $b[1]);
    });
    foreach (array_reverse($list) as $pair) {
        audit_fund_mysql_release_lock($pdo, audit_fund_booking_day_lock_name($pair[0], $pair[1]));
    }
}
