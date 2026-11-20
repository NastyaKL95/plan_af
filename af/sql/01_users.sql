-- База: audit_fund
-- Таблица пользователей под форму входа (login.php): поля login, password, remember.

CREATE DATABASE IF NOT EXISTS `audit_fund`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `audit_fund`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  -- Логин (обязателен); то же поле формы name="login" может совпасть с email — тогда ищем по колонке email
  `username` VARCHAR(64) NOT NULL,
  -- Отображаемое имя (шапка, аудит, «кто подал заявку»); если пусто — показываем логин
  `display_name` VARCHAR(191) DEFAULT NULL,
  -- 191 вместо 255 для совместимости с MySQL/MariaDB, где лимит индекса InnoDB = 767 байт
  `email` VARCHAR(191) DEFAULT NULL,
  -- Хеш пароля (password_hash в PHP, алгоритм по умолчанию — обычно bcrypt)
  `password_hash` VARCHAR(255) NOT NULL,
  -- Вход через Яндекс ID (NULL у парольных учётных записей)
  `oauth_provider` VARCHAR(32) DEFAULT NULL,
  `oauth_subject` VARCHAR(191) DEFAULT NULL,
  -- Активна ли учётная запись (мягкая блокировка без удаления строки)
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  -- Роль пользователя: admin может изменять данные и одобрять заявки, user только просматривает и создаёт заявки
  `role` ENUM('admin','manager','user') NOT NULL DEFAULT 'user',
  -- «Запомнить меня»: хранить только хеш токена, не сам токен в открытом виде
  `remember_token_hash` CHAR(64) DEFAULT NULL,
  `remember_token_expires_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`),
  UNIQUE KEY `uk_users_oauth` (`oauth_provider`, `oauth_subject`),
  KEY `idx_users_remember_expires` (`remember_token_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вход: WHERE (username = :login OR email = :login) AND is_active = 1; пароль — password_verify().
-- Чекбокс remember: токен в cookie, в БД — только хеш (например SHA-256 → remember_token_hash CHAR(64)), срок — remember_token_expires_at.
