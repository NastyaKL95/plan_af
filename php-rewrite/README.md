# Конкурсы — PHP

Веб-приложение без фреймворка:

- Backend: PHP 8.2+ (PDO)
- Frontend: Bootstrap + общий `styles.css`, без сборщика
- БД: скрипт MySQL (`database/schema.sql`, при необходимости миграции из `database/migration_*.sql`)

Структура точки входа и маршрутов описана в [`docs/CONVENTIONS.md`](docs/CONVENTIONS.md).

## Установка (веб-мастер)

Как в WordPress/Moodle: загрузите файлы на хостинг, направьте корень сайта на `php-rewrite/public`
и откройте сайт в браузере. Пока нет `app/config.php`, вместо сайта открывается мастер установки:

1. **Проверка сервера** — PHP 8.2+, расширения `pdo_mysql`, `mbstring`, `fileinfo`, `zip`, `openssl`
   (желательно `curl`), права на запись в `storage/logs`, `storage/cache`, `public/uploads` и `app/`.
2. **База данных** — сервер, порт, имя базы, пользователь и пароль MySQL/MariaDB; базу можно создать прямо отсюда.
3. **Сайт и администратор** — название, адрес сайта, email и пароль первого администратора, по желанию демо-конкурс.
   Мастер создаёт таблицы (`database/schema.sql`), системные роли и администратора.
   Если в базе уже есть таблицы сайта, они не трогаются — создаётся только конфиг.
4. **Готово** — мастер записывает `app/config.php`, после чего сам отключается.
   Если каталог `app/` закрыт на запись, мастер покажет текст конфига — сохраните его в `app/config.php` вручную.

Вход через Яндекс и почта настраиваются потом в `app/config.php` (см. раздел «Яндекс и почта»).
Чтобы установить заново, удалите `app/config.php` (и при необходимости очистите базу).

## Ручная установка

1. Create database and execute `database/schema.sql` (optionally `database/seed.sql` — admin `admin@iro.local` / `password`, смените пароль после входа).
2. Copy `app/config.example.php` to `app/config.php` and update credentials.
3. Start PHP server from `php-rewrite/public`:

```bash
php -S localhost:8080 index.php
```

`index.php` в роли роутера встроенного сервера отдаёт статику сам, а приватные загрузки — только через проверку прав.

4. Open <http://localhost:8080>.

## Run in OpenServer (Windows)

1. In OpenServer select modules: **Apache** + **PHP 8.2+** + **MySQL 8+**.
2. Add a domain (for example `iro.local`) with document root pointing to `php-rewrite/public`.
3. Open `http://iro.local/` — запустится мастер установки (см. выше).
   Либо вручную: импортируйте `database/schema.sql` (и `database/seed.sql`) и создайте `app/config.php`.

## Implemented in this rewrite

- Public pages: `/`, `/contests`, `/contest?id=...`, `/news`, `/news/show?id=...`
- Auth and account: `/signup`, `/login`, `/logout`, `/profile`
- Password reset: `/forgot-password`, `/reset-password` (нужны `migration_password_reset_v1.sql` и SMTP в `mail`)
- Яндекс OAuth: `/auth/yandex`, `/auth/yandex/callback` (без ограничения домена email; `migration_oauth_yandex_v1.sql`)
- User cabinet: `/my-contests`, `/notifications`
- Session auth by email/password hash and profile editing; неактивные (`is_active=0`) не входят
- Общая тема Bootstrap + кастомный `public/assets/css/styles.css`
- SQL structure script covering core entities from Django models

## Яндекс и почта

1. Выполните миграции `database/migration_oauth_yandex_v1.sql` и `database/migration_password_reset_v1.sql` (если БД уже была развёрнута раньше).
2. В `app/config.php` включите `oauth.yandex` (client_id / client_secret / redirect_uri) и при необходимости `mail` для писем сброса пароля.
3. В кабинете Яндекс OAuth укажите Redirect URI вида `https://ваш-домен/auth/yandex/callback`.
4. Для ссылок в письмах задайте `app.public_origin` (например `https://konkurs.zabedu.ru`).

## Приватные файлы

Каталоги `public/uploads/{consents,participant_contest_files,submissions,submission_optional,contest_archive_reports}`
не отдаются напрямую: правило в `public/.htaccess` направляет такие запросы в `index.php`, который проверяет права
(владелец, модератор/эксперт конкурса или администратор). На nginx нужно аналогичное правило:
`location ~ ^/uploads/(consents|participant_contest_files|submissions|submission_optional|contest_archive_reports)/ { rewrite ^ /index.php last; }`.

## Разработка

- Установка зависимостей для форматирования кода (опционально): `composer install`, затем `vendor/bin/php-cs-fixer fix`.
- Ожидаемый конфиг приложения: скопировать `app/config.example.php` → `app/config.php` (см. `.gitignore`).
