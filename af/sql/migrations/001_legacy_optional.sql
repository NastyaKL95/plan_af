-- Опционально: для старых копий БД без этих объектов.
-- Пропустите файл, если база создана актуальными sql/01_users.sql, sql/03_domain.sql.

USE `audit_fund`;

-- Соответствует auth.php / audit_fund_users_ensure_role_column (если колонки ещё нет).
-- ALTER TABLE `users` ADD COLUMN `role` ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER `is_active`;

-- Соответствует management_data / schedule_data (если color_hex ещё нет в rooms).
-- ALTER TABLE `rooms` ADD COLUMN `color_hex` CHAR(7) NOT NULL DEFAULT '#3B82F6' AFTER `room_number`;

-- Соответствует requests_data (если колонки ещё нет).
-- ALTER TABLE `booking_requests` ADD COLUMN `rejection_reason` VARCHAR(1000) DEFAULT NULL AFTER `status`;

-- Полные определения таблиц — в sql/03_domain.sql (CREATE TABLE IF NOT EXISTS …).
