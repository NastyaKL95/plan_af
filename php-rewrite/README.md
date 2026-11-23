# Конкурсы — PHP

Веб-приложение без фреймворка:

- Backend: PHP 8.2+ (PDO)
- Frontend: Bootstrap + общий `styles.css`, без сборщика
- БД: скрипт MySQL (`database/schema.sql`, при необходимости миграции из `database/migration_*.sql`)

Структура точки входа и маршрутов описана в [`docs/CONVENTIONS.md`](docs/CONVENTIONS.md).

## Run locally

1. Create database and execute `database/schema.sql` (optionally `database/seed.sql`).
2. Copy `app/config.example.php` to `app/config.php` and update credentials.
3. Start PHP server from `php-rewrite/public`:

```bash
php -S localhost:8080
```

4. Open <http://localhost:8080>.

## Run in OpenServer (Windows)

1. In OpenServer select modules: **Apache** + **PHP 8.2+** + **MySQL 8+**.
2. Add a domain (for example `iro.local`) with document root pointing to `php-rewrite/public`.
3. Create a MySQL database (for example `iro_php`) and import:
   - `database/schema.sql`
   - optional `database/seed.sql`
4. Set DB credentials in `app/config.php`.
5. Open `http://iro.local/`.

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

## Разработка

- Установка зависимостей для форматирования кода (опционально): `composer install`, затем `vendor/bin/php-cs-fixer fix`.
- Ожидаемый конфиг приложения: скопировать `app/config.example.php` → `app/config.php` (см. `.gitignore`).
