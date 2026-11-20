<?php
declare(strict_types=1);

/**
 * Логика веб-установщика (install/). Без подключения auth.php / audit_fund_pdo().
 */

/**
 * Веб-мастер установки включён (редирект при отсутствии database.local.php и страница install/).
 * Временно отключено — верните true в теле функции, чтобы снова включить.
 */
function audit_fund_install_wizard_enabled(): bool
{
    return true;
}

/** HTML-страница при необработанном исключении в мастере установки (вместо «белого экрана»). */
function audit_fund_install_render_throwable_page(Throwable $e): void
{
    error_log(
        '[audit-fund install] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . (string) $e->getLine()
        . "\n" . $e->getTraceAsString()
    );
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Ошибка установки</title>'
        . '<link rel="icon" href="favicon.ico" sizes="any"><link rel="icon" href="favicon.svg" type="image/svg+xml">'
        . '<link rel="stylesheet" href="../styles.css"></head><body><div class="page"><main class="card card--wide">'
        . '<h1 class="card__title">Ошибка установки</h1>'
        . '<p class="form__error" role="alert">Произошла внутренняя ошибка. Подробности записаны в журнал PHP сервера (error_log).</p>'
        . '<p class="form__hint">Проверьте журнал PHP в OpenServer и что включены расширения pdo_mysql и mbstring.</p>'
        . '</main></div></body></html>';
}

/**
 * Нормализует путь из SCRIPT_NAME для ссылок и заголовка Location.
 * Без ведущего «/» относительный редирект ломается (дублируется сегмент каталога, пустая страница).
 */
function audit_fund_install_normalize_script_web_path(?string $scriptName): string
{
    $s = str_replace('\\', '/', trim((string) ($scriptName ?? '')));
    if ($s === '') {
        return '';
    }

    return '/' . ltrim($s, '/');
}

/**
 * Путь к install/index.php в URL (надёжнее, чем относительный index.php в Location и в action формы).
 * Цепочка SCRIPT_NAME → PHP_SELF → REQUEST_URI: на части конфигов OpenServer/прокси одно из значений бывает пустым или неверным.
 */
function audit_fund_install_script_url_path(): string
{
    $candidates = [
        $_SERVER['SCRIPT_NAME'] ?? '',
        $_SERVER['PHP_SELF'] ?? '',
    ];
    $reqPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (is_string($reqPath)) {
        $candidates[] = $reqPath;
    }

    $fallback = '';
    foreach ($candidates as $raw) {
        $s = audit_fund_install_normalize_script_web_path(is_string($raw) ? $raw : '');
        if ($s === '') {
            continue;
        }
        if (stripos($s, '/install') !== false) {
            if (preg_match('#/install/?$#i', $s)) {
                return rtrim($s, '/') . '/index.php';
            }

            return $s;
        }
        if ($fallback === '') {
            $fallback = $s;
        }
    }

    if ($fallback !== '') {
        return $fallback;
    }

    return '/install/index.php';
}

function audit_fund_install_ensure_storage_dirs(string $root): void
{
    $dirs = [
        $root . '/storage/login_rate_limit',
        $root . '/storage/register_rate_limit',
    ];
    foreach ($dirs as $d) {
        if (!is_dir($d)) {
            if (!@mkdir($d, 0775, true) && !is_dir($d)) {
                @error_log('audit-fund install: не удалось создать каталог ' . $d);
            }
        }
    }
}

/** @return list<string> */
function audit_fund_install_requirements_errors(): array
{
    $errors = [];
    if (version_compare(PHP_VERSION, '8.1.0', '<')) {
        $errors[] = 'Нужен PHP 8.1 или новее (сейчас ' . PHP_VERSION . ').';
    }
    if (!extension_loaded('pdo')) {
        $errors[] = 'Расширение PDO не включено.';
    }
    if (!extension_loaded('pdo_mysql')) {
        $errors[] = 'Расширение pdo_mysql не включено (нужно для MySQL/MariaDB).';
    }
    if (!extension_loaded('json')) {
        $errors[] = 'Расширение json не включено.';
    }
    if (!function_exists('mb_strlen')) {
        $errors[] = 'Нужно расширение mbstring (имена и валидация текста).';
    }

    $root = dirname(__DIR__);
    if (!is_dir($root . '/config')) {
        $errors[] = 'Отсутствует каталог config/.';
    } elseif (!is_writable($root . '/config')) {
        $errors[] = 'Каталог config/ недоступен для записи (нужен для database.local.php).';
    }

    $storageDirs = [
        $root . '/storage/login_rate_limit',
        $root . '/storage/register_rate_limit',
    ];
    foreach ($storageDirs as $d) {
        if (!is_dir($d)) {
            $errors[] = 'Создайте каталог: ' . basename(dirname($d)) . '/' . basename($d);
        } elseif (!is_writable($d)) {
            $errors[] = 'Нет прав на запись: ' . str_replace($root, '', $d);
        }
    }

    return $errors;
}

function audit_fund_install_validate_db_name(string $name): ?string
{
    $name = trim($name);
    if ($name === '') {
        return 'Укажите имя базы данных.';
    }
    if (strlen($name) > 64) {
        return 'Имя базы не длиннее 64 символов.';
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        return 'Имя базы: только латиница, цифры и подчёркивание.';
    }

    return null;
}

/**
 * Подстановка имени БД в дампах (вместо audit_fund).
 */
function audit_fund_install_substitute_database(string $sql, string $dbName): string
{
    return str_replace('`audit_fund`', '`' . $dbName . '`', $sql);
}

/**
 * @param array{host:string,port:int,user:string,password:string,database:string,charset:string} $cfg
 */
function audit_fund_install_pdo_connect_server(array $cfg): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;charset=%s',
        $cfg['host'],
        $cfg['port'],
        $cfg['charset']
    );

    return new PDO($dsn, $cfg['user'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);
}

/**
 * @param array{host:string,port:int,user:string,password:string,database:string,charset:string} $cfg
 */
function audit_fund_install_pdo_connect_database(array $cfg): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['port'],
        $cfg['database'],
        $cfg['charset']
    );

    return new PDO($dsn, $cfg['user'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);
}

/**
 * Выполнить один .sql файл (мульти-стейтменты).
 */
function audit_fund_install_exec_sql_file(PDO $pdo, string $path, string $dbName): void
{
    if (!is_readable($path)) {
        throw new RuntimeException('Файл не найден: ' . $path);
    }
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Пустой или нечитаемый файл: ' . $path);
    }
    $sql = audit_fund_install_substitute_database($sql, $dbName);
    $pdo->exec($sql);
}

/**
 * @param array{host:string,port:int,user:string,password:string,database:string,charset:string} $dbCfg
 * @return array{ok:bool,error:?string}
 */
function audit_fund_install_try_connection(array $dbCfg): array
{
    try {
        $pdo = audit_fund_install_pdo_connect_server($dbCfg);
        $pdo->query('SELECT 1');
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    return ['ok' => true, 'error' => null];
}

/**
 * Полная установка схемы и опционально демо-данных.
 *
 * @param array{host:string,port:int,user:string,password:string,database:string,charset:string} $dbCfg
 * @return array{ok:bool,error:?string}
 */
function audit_fund_install_run_schema(array $dbCfg, bool $withDemoData): array
{
    $root = dirname(__DIR__);
    $dbName = $dbCfg['database'];

    try {
        $pdo = audit_fund_install_pdo_connect_server($dbCfg);
        audit_fund_install_exec_sql_file($pdo, $root . '/sql/01_users.sql', $dbName);
        $pdoDb = audit_fund_install_pdo_connect_database($dbCfg);
        audit_fund_install_exec_sql_file($pdoDb, $root . '/sql/03_domain.sql', $dbName);
        if ($withDemoData) {
            audit_fund_install_exec_sql_file($pdoDb, $root . '/sql/04_seed_domain.sql', $dbName);
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    return ['ok' => true, 'error' => null];
}

/**
 * Создать первого администратора (после схемы).
 *
 * @param array{host:string,port:int,user:string,password:string,database:string,charset:string} $dbCfg
 * @return array{ok:bool,error:?string}
 */
function audit_fund_install_create_admin(
    array $dbCfg,
    string $username,
    string $displayName,
    string $email,
    string $password
): array {
    $username = trim($username);
    $displayName = trim($displayName);
    $email = trim($email);

    try {
        $pdo = audit_fund_install_pdo_connect_database($dbCfg);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            return ['ok' => false, 'error' => 'Не удалось сформировать хеш пароля.'];
        }
        $emailDb = $email !== '' ? $email : null;
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, display_name, email, password_hash, is_active, role) VALUES (?, ?, ?, ?, 1, "admin")'
        );
        $stmt->execute([$username, $displayName, $emailDb, $hash]);
    } catch (Throwable $e) {
        if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
            return ['ok' => false, 'error' => 'Пользователь с таким логином или e-mail уже существует.'];
        }

        return ['ok' => false, 'error' => $e->getMessage()];
    }

    return ['ok' => true, 'error' => null];
}

/**
 * @param array{host:string,port:int,database:string,user:string,password:string,charset:string,runtime_migrations:bool,registration_open:bool,session_cookie_lifetime:int,session_cookie_secure:?bool,session_cookie_samesite:string} $cfg
 */
function audit_fund_install_write_config(array $cfg): ?string
{
    $root = dirname(__DIR__);
    $path = $root . '/config/database.local.php';
    if (!is_writable(dirname($path))) {
        return 'Нет прав на запись в каталог config/.';
    }

    $secure = $cfg['session_cookie_secure'];
    $securePhp = $secure === null ? 'null' : ($secure ? 'true' : 'false');

    $content = "<?php\n"
        . "declare(strict_types=1);\n\n"
        . "/**\n"
        . " * Локальная конфигурация БД и политик (создано мастером установки install/).\n"
        . " * Не публикуйте этот файл в открытый репозиторий.\n"
        . " */\n"
        . "return [\n"
        . "    'host' => " . var_export($cfg['host'], true) . ",\n"
        . "    'port' => " . (int) $cfg['port'] . ",\n"
        . "    'database' => " . var_export($cfg['database'], true) . ",\n"
        . "    'user' => " . var_export($cfg['user'], true) . ",\n"
        . "    'password' => " . var_export($cfg['password'], true) . ",\n"
        . "    'charset' => " . var_export($cfg['charset'], true) . ",\n"
        . "    'runtime_migrations' => " . ($cfg['runtime_migrations'] ? 'true' : 'false') . ",\n"
        . "    'registration_open' => " . ($cfg['registration_open'] ? 'true' : 'false') . ",\n"
        . "    'session_cookie_lifetime' => " . (int) $cfg['session_cookie_lifetime'] . ",\n"
        . "    'session_cookie_secure' => {$securePhp},\n"
        . "    'session_cookie_samesite' => " . var_export($cfg['session_cookie_samesite'], true) . ",\n"
        . "];\n";

    if (file_put_contents($path, $content) === false) {
        return 'Не удалось записать config/database.local.php.';
    }

    return null;
}
