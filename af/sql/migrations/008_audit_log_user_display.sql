-- Колонка: отображаемое имя из сессии (раньше username — путаница с логином).
USE `audit_fund`;

ALTER TABLE `audit_log`
  CHANGE COLUMN `username` `user_display` VARCHAR(191) NULL DEFAULT NULL;
