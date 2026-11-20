<?php
/**
 * Применение SQL: пользователи, доменная модель (аудитории, расписание, заявки), демо-данные.
 * Для хостинга чаще удобнее веб-мастер: откройте в браузере install/
 *
 * Консоль (OpenServer, VPS):
 *   php scripts/install_db.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/database.php';

$c = audit_fund_db_config();

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%d;charset=%s',
        $c['host'],
        (int) $c['port'],
        $c['charset']
    ),
    $c['user'],
    $c['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]
);

foreach (['01_users.sql', '02_seed.sql', '03_domain.sql', '04_seed_domain.sql'] as $file) {
    $path = $root . '/sql/' . $file;
    if (!is_readable($path)) {
        fwrite(STDERR, "Файл не найден: {$path}\n");
        exit(1);
    }
    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Не удалось прочитать: {$path}\n");
        exit(1);
    }
    $pdo->exec($sql);
}

echo "База audit_fund: users, справочники, аудитории, расписание, заявки; демо-данные и пользователь audit_fund.\n";
