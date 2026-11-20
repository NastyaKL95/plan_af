-- Начальный пользователь (как в старом auth.php): логин audit_fund, пароль audit_fund_2025
-- Хеш bcrypt сгенерирован для пароля audit_fund_2025 (совместим с password_verify в PHP).

USE `audit_fund`;

INSERT IGNORE INTO `users` (`username`, `display_name`, `email`, `password_hash`, `is_active`, `role`)
VALUES (
  'audit_fund',
  'Администратор',
  NULL,
  '$2b$10$ggdhXOKtvUq66kAspzitf.NwzQqyoXkZF8oLVFujagONEWx/1w66G',
  1,
  'admin'
);

-- Назначить администратором существующего пользователя admin2026
UPDATE `users`
SET `role` = 'admin'
WHERE `username` = 'admin2026';
