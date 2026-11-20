Дополнительные скрипты для баз, развёрнутых до актуальной схемы sql/01_users.sql … sql/03_domain.sql

Назначение
----------
В коде PHP при runtime_migrations = true часть изменений применяется автоматически
(ALTER / CREATE TABLE IF NOT EXISTS). На продакшене рекомендуется runtime_migrations = false
и перенос изменений вручную или через эти файлы.

Порядок
-------
Выполняйте по номеру только те шаги, которых ещё нет в вашей БД (проверьте SHOW COLUMNS /
SHOW TABLES). Актуальная полная схема — в sql/03_domain.sql и sql/01_users.sql.

Файлы
-----
001_legacy_optional.sql — типовые доработки, которые дублирует runtime-код приложения
(колонки и таблицы; безопасно пропустить, если база создана свежими sql/*.sql).

006_users_role_manager.sql — значение manager в ENUM колонки users.role (при отключённых
runtime_migrations выполните вручную; при true может добавить auth.php при первом запросе).

009_users_oauth_yandex.sql — колонки users.oauth_provider и users.oauth_subject для входа
через Яндекс. На продакшене выполните вручную: общий вход по email работает и без них,
привязка идентификатора Яндекса — после этой миграции.

После применения миграций установите в database.local.php 'runtime_migrations' => false.
