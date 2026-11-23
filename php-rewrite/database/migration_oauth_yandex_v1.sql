-- Вход через Яндекс OAuth (как в Планировщике).
-- Выполните один раз. Если колонка/индекс уже есть — MySQL вернёт 1060/1061, это нормально.
SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN oauth_provider VARCHAR(32) NULL AFTER password_hash,
  ADD COLUMN oauth_subject VARCHAR(191) NULL AFTER oauth_provider;

ALTER TABLE users
  ADD UNIQUE KEY uq_users_oauth (oauth_provider, oauth_subject);
