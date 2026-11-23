-- Вход через Яндекс и общий сеанс с Вектором.
-- Если колонка или индекс уже есть, MySQL вернёт 1060/1061 — это нормально, шаг можно пропустить.
SET NAMES utf8mb4;

ALTER TABLE users ADD COLUMN oauth_provider VARCHAR(32) NULL DEFAULT NULL AFTER password_hash;
ALTER TABLE users ADD COLUMN oauth_subject VARCHAR(191) NULL DEFAULT NULL AFTER oauth_provider;
ALTER TABLE users ADD UNIQUE KEY uk_users_oauth (oauth_provider, oauth_subject);
