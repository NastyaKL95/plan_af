<?php

declare(strict_types=1);

/**
 * Веб-установщик (как в WordPress / Moodle): запускается из public/index.php, пока нет app/config.php.
 * Шаги: проверка окружения → подключение к MySQL → сайт и администратор → создание таблиц и config.php.
 * После появления config.php установщик недоступен.
 */

const IRO_INSTALLER_MIN_PHP = '8.2.0';

/** @return list<array{label: string, ok: bool, required: bool, hint: string}> */
function iro_installer_requirements(): array
{
    $checks = [];
    $checks[] = [
        'label' => 'PHP ' . IRO_INSTALLER_MIN_PHP . ' или новее (сейчас ' . PHP_VERSION . ')',
        'ok' => version_compare(PHP_VERSION, IRO_INSTALLER_MIN_PHP, '>='),
        'required' => true,
        'hint' => 'Обновите PHP на хостинге.',
    ];
    foreach ([
        'pdo_mysql' => ['Расширение pdo_mysql', true, 'Нужно для работы с базой MySQL.'],
        'mbstring' => ['Расширение mbstring', true, 'Нужно для работы с русским текстом.'],
        'fileinfo' => ['Расширение fileinfo', true, 'Нужно для проверки типа загружаемых файлов.'],
        'zip' => ['Расширение zip', true, 'Нужно для выгрузки Excel и архивов работ.'],
        'openssl' => ['Расширение openssl', true, 'Нужно для почты (SMTP) и входа через Яндекс.'],
        'curl' => ['Расширение curl', false, 'Желательно для входа через Яндекс (без него используются потоки PHP).'],
    ] as $ext => [$label, $required, $hint]) {
        $checks[] = ['label' => $label, 'ok' => extension_loaded($ext), 'required' => $required, 'hint' => $hint];
    }
    foreach (['app', 'storage/logs', 'storage/cache', 'public/uploads'] as $dir) {
        $path = env_path($dir);
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        $checks[] = [
            'label' => 'Запись в каталог ' . $dir . '/',
            'ok' => is_dir($path) && is_writable($path),
            'required' => $dir !== 'app',
            'hint' => $dir === 'app'
                ? 'Без этого config.php не запишется автоматически — его покажут в конце, сохраните вручную.'
                : 'Дайте веб-серверу права на запись.',
        ];
    }
    $checks[] = [
        'label' => 'Файл database/schema.sql',
        'ok' => is_file(env_path('database/schema.sql')),
        'required' => true,
        'hint' => 'Загрузите сайт целиком, включая каталог database.',
    ];

    return $checks;
}

/** @param list<array{ok: bool, required: bool}> $checks */
function iro_installer_requirements_ok(array $checks): bool
{
    foreach ($checks as $c) {
        if ($c['required'] && !$c['ok']) {
            return false;
        }
    }

    return true;
}

/** Базовый URL-префикс (если сайт открыт из подкаталога). */
function iro_installer_base(): string
{
    $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));

    return ($dir === '/' || $dir === '.' || $dir === '') ? '' : rtrim($dir, '/');
}

function iro_installer_detect_origin(): string
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $isHttps = ($https !== '' && $https !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) {
        $host = 'localhost';
    }

    return ($isHttps ? 'https' : 'http') . '://' . $host . iro_installer_base();
}

/**
 * @param array{host: string, port: int, dbname: string, user: string, password: string} $db
 */
function iro_installer_connect(array $db, bool $createDatabase): PDO
{
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
        $opts[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = 10;
    }
    $dsnBase = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']);
    try {
        return new PDO($dsnBase . ';dbname=' . $db['dbname'], $db['user'], $db['password'], $opts);
    } catch (PDOException $e) {
        $unknownDb = str_contains($e->getMessage(), '1049') || str_contains($e->getMessage(), 'Unknown database');
        if (!$unknownDb || !$createDatabase) {
            throw $e;
        }
    }
    $server = new PDO($dsnBase, $db['user'], $db['password'], $opts);
    $server->exec('CREATE DATABASE `' . $db['dbname'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    return new PDO($dsnBase . ';dbname=' . $db['dbname'], $db['user'], $db['password'], $opts);
}

function iro_installer_db_has_tables(PDO $pdo): bool
{
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");

    return $stmt !== false && $stmt->fetchColumn() !== false;
}

/** @return list<string> */
function iro_installer_sql_statements(string $sql): array
{
    $sql = str_replace("\r\n", "\n", $sql);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $out = [];
    foreach (preg_split('/;\s*\n/', $sql) ?: [] as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || preg_match('/^(START TRANSACTION|COMMIT)$/i', $stmt)) {
            continue;
        }
        $out[] = $stmt;
    }

    return $out;
}

/**
 * @param array{email: string, password: string, first_name: string, last_name: string} $admin
 */
function iro_installer_create_schema(PDO $pdo, array $admin, bool $demo): void
{
    foreach (iro_installer_sql_statements((string) file_get_contents(env_path('database/schema.sql'))) as $stmt) {
        $pdo->exec($stmt);
    }
    $pdo->exec(
        "INSERT INTO system_roles (id, slug, name) VALUES
           (1, 'admin', 'Администратор'), (2, 'moderator', 'Модератор'),
           (3, 'expert', 'Эксперт'), (4, 'participant', 'Участник')
         ON DUPLICATE KEY UPDATE slug = VALUES(slug), name = VALUES(name)"
    );
    $pdo->prepare(
        'INSERT INTO users (email, password_hash, first_name, last_name, is_active, is_staff, is_superuser)
         VALUES (:email, :hash, :fn, :ln, 1, 1, 1)'
    )->execute([
        'email' => $admin['email'],
        'hash' => password_hash($admin['password'], PASSWORD_DEFAULT),
        'fn' => $admin['first_name'] !== '' ? $admin['first_name'] : null,
        'ln' => $admin['last_name'] !== '' ? $admin['last_name'] : null,
    ]);
    $adminId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, 1)')->execute(['u' => $adminId]);

    if ($demo) {
        $year = (int) date('Y');
        $pdo->prepare(
            'INSERT INTO contests (title, start_date, end_date, description, status) VALUES (:t, :s, :e, :d, 1)'
        )->execute([
            't' => 'Демо-конкурс',
            's' => $year . '-01-10',
            'e' => $year . '-12-31',
            'd' => 'Тестовые данные. Конкурс в статусе черновика — виден только администратору.',
        ]);
        $contestId = (int) $pdo->lastInsertId();
        $st = $pdo->prepare(
            'INSERT INTO stages (contest_id, title, description, sort_order, start_date, end_date) VALUES (:c, :t, :d, :o, :s, :e)'
        );
        $st->execute(['c' => $contestId, 't' => 'Этап 1', 'd' => 'Первый этап (демо).', 'o' => 1, 's' => $year . '-01-10', 'e' => $year . '-06-30']);
        $st->execute(['c' => $contestId, 't' => 'Этап 2', 'd' => 'Второй этап (демо).', 'o' => 2, 's' => $year . '-07-01', 'e' => $year . '-12-31']);
    }
}

/**
 * config.php на основе config.example.php: подставляются база, название, публичный адрес и redirect_uri Яндекса.
 *
 * @param array{host: string, port: int, dbname: string, user: string, password: string} $db
 */
function iro_installer_build_config(array $db, string $siteName, string $origin): string
{
    $tpl = (string) file_get_contents(env_path('app/config.example.php'));
    $e = static fn ($v): string => var_export($v, true);
    $nl = str_contains($tpl, "\r\n") ? "\r\n" : "\n";

    $dbBlock = "'db' => [" . $nl
        . "        'host' => " . $e($db['host']) . ',' . $nl
        . "        'port' => " . (int) $db['port'] . ',' . $nl
        . "        'dbname' => " . $e($db['dbname']) . ',' . $nl
        . "        'user' => " . $e($db['user']) . ',' . $nl
        . "        'password' => " . $e($db['password']) . ',' . $nl
        . '    ],';
    $tpl = preg_replace_callback("/'db' => \\[.*?\\],/s", static fn (): string => $dbBlock, $tpl, 1) ?? $tpl;
    $tpl = preg_replace_callback(
        "/('app' => \\[\\r?\\n\\s*)'name' => '[^']*',/",
        static fn (array $m): string => $m[1] . "'name' => " . $e($siteName) . ',',
        $tpl,
        1
    ) ?? $tpl;
    $tpl = preg_replace_callback(
        "~// 'public_origin' => '[^']*',~",
        static fn (): string => "'public_origin' => " . $e($origin) . ',',
        $tpl,
        1
    ) ?? $tpl;
    $tpl = str_replace("'https://example.ru/auth/yandex/callback'", $e($origin . '/auth/yandex/callback'), $tpl);
    if (str_starts_with($origin, 'https://')) {
        $tpl = str_replace("// 'session_cookie_secure' => true,", "'session_cookie_secure' => true,", $tpl);
    }

    return $tpl;
}

/** Проверяет, что сгенерированный конфиг — корректный PHP, возвращающий массив с базой. */
function iro_installer_config_valid(string $content): bool
{
    $tmp = env_path('storage/cache/config-check-' . bin2hex(random_bytes(6)) . '.php');
    if (@file_put_contents($tmp, $content) === false) {
        return true;
    }
    try {
        $cfg = (static fn (string $f) => require $f)($tmp);

        return is_array($cfg) && isset($cfg['db']['host'], $cfg['db']['dbname']);
    } catch (Throwable) {
        return false;
    } finally {
        @unlink($tmp);
    }
}

function iro_installer_write_config(string $configPath, string $content): bool
{
    $tmp = $configPath . '.tmp-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
        return false;
    }
    @chmod($tmp, 0640);
    if (!@rename($tmp, $configPath)) {
        @unlink($tmp);

        return false;
    }

    return true;
}

function iro_installer_csrf(): string
{
    if (empty($_SESSION['iro_install_csrf'])) {
        $_SESSION['iro_install_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['iro_install_csrf'];
}

function iro_installer_redirect(string $step): never
{
    header('Location: ' . iro_installer_base() . '/?install_step=' . rawurlencode($step));
    exit;
}

function iro_installer_run(string $configPath): never
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('iro_install');
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    $step = (string) ($_GET['install_step'] ?? 'check');
    $errors = [];
    $checks = iro_installer_requirements();
    $reqOk = iro_installer_requirements_ok($checks);
    $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($isPost && !hash_equals(iro_installer_csrf(), (string) ($_POST['csrf'] ?? ''))) {
        $errors[] = 'Сессия устарела — обновите страницу и повторите.';
        $isPost = false;
    }
    if (!$reqOk && $step !== 'check') {
        iro_installer_redirect('check');
    }

    $db = $_SESSION['iro_install_db'] ?? null;
    $form = [];

    if ($step === 'db') {
        $form = is_array($db) ? $db : ['host' => 'localhost', 'port' => 3306, 'dbname' => 'konkurs', 'user' => '', 'password' => ''];
        if ($isPost) {
            $form = [
                'host' => trim((string) ($_POST['host'] ?? '')),
                'port' => (int) ($_POST['port'] ?? 3306),
                'dbname' => trim((string) ($_POST['dbname'] ?? '')),
                'user' => trim((string) ($_POST['user'] ?? '')),
                'password' => (string) ($_POST['password'] ?? ''),
            ];
            if ($form['host'] === '' || $form['user'] === '') {
                $errors[] = 'Укажите сервер и пользователя базы данных.';
            }
            if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $form['dbname'])) {
                $errors[] = 'Имя базы: только латинские буквы, цифры и «_».';
            }
            if ($form['port'] <= 0 || $form['port'] > 65535) {
                $form['port'] = 3306;
            }
            if (!$errors) {
                try {
                    $pdo = iro_installer_connect($form, isset($_POST['create_db']));
                    $_SESSION['iro_install_db'] = $form;
                    $_SESSION['iro_install_existing'] = iro_installer_db_has_tables($pdo);
                    iro_installer_redirect('site');
                } catch (Throwable $e) {
                    $errors[] = 'Не удалось подключиться к базе: ' . $e->getMessage();
                }
            }
        }
    }

    $existing = !empty($_SESSION['iro_install_existing']);
    $configContent = null;
    $configWritten = false;

    if ($step === 'site') {
        if (!is_array($db)) {
            iro_installer_redirect('db');
        }
        $form = [
            'site_name' => 'Конкурсы',
            'origin' => iro_installer_detect_origin(),
            'email' => '',
            'first_name' => '',
            'last_name' => '',
            'demo' => false,
        ];
        if ($isPost) {
            $form = [
                'site_name' => trim((string) ($_POST['site_name'] ?? '')),
                'origin' => rtrim(trim((string) ($_POST['origin'] ?? '')), '/'),
                'email' => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
                'first_name' => trim((string) ($_POST['first_name'] ?? '')),
                'last_name' => trim((string) ($_POST['last_name'] ?? '')),
                'demo' => isset($_POST['demo']),
            ];
            $password = (string) ($_POST['password'] ?? '');
            if ($form['site_name'] === '') {
                $form['site_name'] = 'Конкурсы';
            }
            if (!preg_match('~^https?://[^\s/]+(/[^\s]*)?$~i', $form['origin'])) {
                $errors[] = 'Адрес сайта должен начинаться с http:// или https://.';
            }
            if (!$existing) {
                if (filter_var($form['email'], FILTER_VALIDATE_EMAIL) === false) {
                    $errors[] = 'Укажите корректный email администратора.';
                }
                if (mb_strlen($password) < 8) {
                    $errors[] = 'Пароль администратора — не короче 8 символов.';
                } elseif ($password !== (string) ($_POST['password_confirm'] ?? '')) {
                    $errors[] = 'Пароль и подтверждение не совпадают.';
                }
            }
            if (!$errors) {
                try {
                    $pdo = iro_installer_connect($db, false);
                    if (!$existing) {
                        if (iro_installer_db_has_tables($pdo)) {
                            throw new RuntimeException('в базе уже есть таблицы сайта — вернитесь на шаг базы данных.');
                        }
                        iro_installer_create_schema($pdo, [
                            'email' => $form['email'],
                            'password' => $password,
                            'first_name' => $form['first_name'],
                            'last_name' => $form['last_name'],
                        ], $form['demo']);
                    }
                    $configContent = iro_installer_build_config($db, $form['site_name'], $form['origin']);
                    if (!iro_installer_config_valid($configContent)) {
                        throw new RuntimeException('не удалось сформировать config.php из config.example.php.');
                    }
                    $configWritten = iro_installer_write_config($configPath, $configContent);
                    $_SESSION['iro_install_existing'] = true;
                    $step = 'done';
                } catch (Throwable $e) {
                    $errors[] = 'Ошибка установки: ' . $e->getMessage();
                }
            }
        }
    }

    if ($step === 'done' && $configWritten) {
        unset($_SESSION['iro_install_db'], $_SESSION['iro_install_existing']);
    }
    if (!in_array($step, ['check', 'db', 'site', 'done'], true)) {
        $step = 'check';
    }
    if ($step === 'done' && $configContent === null) {
        iro_installer_redirect('check');
    }

    $base = iro_installer_base();
    $csrf = iro_installer_csrf();
    require env_path('views/install/page.php');
    exit;
}
