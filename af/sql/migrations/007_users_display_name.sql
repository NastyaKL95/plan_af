-- Имя для отображения (шапка, аудит, автор заявки).
USE `audit_fund`;

ALTER TABLE `users`
  ADD COLUMN `display_name` VARCHAR(191) NULL DEFAULT NULL AFTER `username`;
