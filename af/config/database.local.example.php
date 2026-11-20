<?php
/**
 * Скопируйте в database.local.php и задайте параметры MySQL.
 *
 * Установка на хостинг: откройте в браузере каталог install/ — мастер создаст
 * этот файл и таблицы сам (аналог первого шага WordPress).
 *
 * Локально без database.local.php действуют значения по умолчанию из includes/database.php.
 *
 * ─── Чеклист перед продакшеном ─────────────────────────────────────────────
 * 1. Выполнить все скрипты sql/*.sql на целевой базе (порядок по номерам в имени файла).
 * 2. Указать надёжные host / user / password, не оставлять root без пароля.
 * 3. Установить 'runtime_migrations' => false — отключить CREATE/ALTER при HTTP-запросах.
 * 4. Установить 'registration_open' => false — закрыть самостоятельную регистрацию.
 * 5. За HTTPS включить 'session_cookie_secure' => true (или null оставляет автоопределение).
 * 6. Убедиться, что каталоги storage/login_rate_limit и storage/register_rate_limit доступны для записи PHP (лимиты входа и регистрации).
 * 7. Настроить резервное копирование БД и права файлов (database.local.php не в публичный репозиторий).
 * 8. При необходимости: composer install в корне проекта (опционально подключает vendor/autoload.php).
 * 9. Для старых БД см. sql/migrations/README.txt — перенос изменений без runtime_migrations.
 * 10. Удобство и масштаб данных: оценить рост таблиц (заявки, расписание, аудит-лог, отчёты) — при необходимости
 *     ввести пагинацию и лимиты выборок, проверить индексы под типичные фильтры и сортировки, не отдавать в интерфейс
 *     неограниченные списки за один запрос.
 * 11. 'production' => true — не показывать ошибки PHP в браузере (или задайте в окружении AUDIT_FUND_PRODUCTION=true).
 */
declare(strict_types=1);

return [
    // 'host' => '127.0.0.1',
    // 'port' => 3306,
    // 'database' => 'audit_fund',
    // 'user' => 'root',
    'password' => '',

    // 'runtime_migrations' => false,
    // 'production' => true,
    // 'registration_open' => false,
    // 'session_cookie_lifetime' => 0,
    // 'session_cookie_secure' => true,
    // 'session_cookie_samesite' => 'Lax',
];
