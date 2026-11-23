# Соглашения по коду (PHP rewrite)

## Данные для шаблонов (`views/`)

- Ключи массива, передаваемого в `render()`, используйте в **`snake_case`**: `is_expert`, `notif_unread_count`, `stage_detail_active_tab`, `contest_stats`. Так же выровнено с `$GLOBALS['base_view_data']`.
- Локальные переменные внутри одного `.php`-шаблона — по смыслу: часто короткие имена (`$c`, `$st`) или **`snake_case`** для ясности (`$req_adm`).
- В шаблоне не полагайтесь на автозагрузку классов: только функции из `helpers.php` и переданные данные.

## PHP

- В `app/*.php` и точке входа — `declare(strict_types=1);`.
- Файлы в `app/http/` подключаются из `public/index.php`: там общий контекст запроса (`$pdo`, `$uri`, `$user`, …).

## Маршрутизация

- **`public/index.php`** — загрузка конфигурации, сессии, зависимостей и `base_view_data`.
- **`app/http/route_aliases.php`** — функция `iro_legacy_route_aliases()`: «красивые» URL → внутренний `$uri` и `$_GET`.
- **`app/http/web_helpers.php`** — вспомогательные функции роутера (контест, этапы, участник).
- **`app/http/routes.php`** — цепочка `if ($uri === …)` по внутренним путям.

## Стили (CSS)

- Предпочтительно классы в `public/assets/css/styles.css`; **избегайте инлайн-`style`** в шаблонах.
- Утилиты ширины панелей: `iro-panel-narrow` (760px), `iro-panel--w520`, `iro-panel--w640`, `iro-panel--w820`, `iro-form--w36r` / `w40r` / `w48r`.
- Остальные утилиты с префиксом `iro-` см. комментарии в `styles.css` рядом с блоком «Фиксированные ширины».

## Форматирование PHP (опционально)

После `composer install` в корне проекта:

```bash
vendor/bin/php-cs-fixer fix
```

Конфигурация — `.php-cs-fixer.php` (правила близки к PSR-12).

## Архив конкурса

Функция `contest_archive_execute()` в `app/contest_archive.php`: перед удалением данных строится та же книга Excel, что и при выгрузке «Статистика», сохраняется в `public/uploads/contest_archive_reports/`. В таблице `contests` остаются название, даты, статус «скрытый», флаги архива и путь к отчёту; остальные таблицы и файлы по конкурсу очищаются. Нужна миграция `database/migration_contest_archive.sql`.

Список архивных конкурсов только для администратора: **GET `/admin/contests/archived`** (пункт меню «Архив» в админке).
