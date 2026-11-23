<?php

declare(strict_types=1);

/**
 * Проверка подключения к внешней MySQL из app/config.php → external_auth.
 *
 * Если видите «Could not open input file», вы запускаете PHP не из того каталога.
 * Варианты:
 *   1) Дважды щёлкнуть check_external_db.bat в корне проекта php-rewrite.
 *   2) Полный путь к файлу (работает из любой папки), подставьте свой диск/путь:
 *        php D:\OSPanel\home\php-rewrite\scripts\check_external_db.php
 *   3) Сначала перейти в корень проекта, затем:
 *        cd /d D:\OSPanel\home\php-rewrite
 *        php scripts\check_external_db.php
 */

$root = dirname(__DIR__);
$configPath = $root . '/app/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Файл app/config.php не найден.\n");
    exit(1);
}

$config = require $configPath;
require $root . '/app/Database.php';

$ea = $config['external_auth'] ?? null;
if (!is_array($ea) || empty($ea['enabled'])) {
    echo "external_auth отключён (enabled = false) — проверка не требуется.\n";
    exit(0);
}

try {
    $pdo = Database::externalAuthConnection($config);
    if ($pdo === null) {
        fwrite(STDERR, "Не удалось получить PDO (проверьте блок external_auth).\n");
        exit(1);
    }
    $pdo->query('SELECT 1');
    echo "OK: подключение к MySQL и проверка SELECT 1 прошли успешно.\n";

    $cfgDb = trim((string) ($ea['dbname'] ?? ''));
    echo "В конфиге external_auth → dbname: " . ($cfgDb !== '' ? $cfgDb : '(пусто)') . "\n";
    $currentDb = $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo 'После подключения SELECT DATABASE(): ' . ($currentDb !== false && $currentDb !== null && $currentDb !== ''
        ? (string) $currentDb
        : '(пусто)') . "\n";

    $table = (string) ($ea['table'] ?? '');
    $tableRef = Database::externalAuthTableReference($table);
    if ($tableRef === null) {
        fwrite(STDERR, "В external_auth.table задайте имя таблицы или полное имя: база.таблица (латиница, цифры, _).\n");
        exit(1);
    }
    $stmt = $pdo->query('SELECT COUNT(*) AS c FROM ' . $tableRef);
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    $c = is_array($row) ? (int) ($row['c'] ?? 0) : 0;
    echo "Таблица {$tableRef}: записей — {$c}.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . "\n");
    if (str_contains($e->getMessage(), '2002')) {
        fwrite(STDERR, "\n[2002] С этой машины до MySQL по адресу из external_auth (host/port) нет TCP-соединения.\n");
        fwrite(STDERR, "Проверьте: ПК в одной сети с сервером, ping, порт 3306 не блокируется фаерволом,\n");
        fwrite(STDERR, "на сервере MySQL слушает внешние интерфейсы (bind-address), сервер включён.\n");
        fwrite(STDERR, "Сайт на хостинге не достучится до 192.168.x.x — только внутри вашей LAN или через VPN/SSH-туннель.\n");
    }
    exit(1);
}
