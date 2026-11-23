-- Роль manager: те же права в данных, что admin, кроме страницы назначения ролей (только admin).
-- При runtime_migrations = true ALTER может выполнить auth.php (audit_fund_users_ensure_role_column).

USE `audit_fund`;

ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('admin','manager','user') NOT NULL DEFAULT 'user';
