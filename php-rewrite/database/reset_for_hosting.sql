-- Полный сброс контента для выкладки на хостинг: конкурсы, этапы, оценки, заявки, новости, уведомления, пользователи.
-- После выполнения остаются только роли и один администратор (как в seed.sql).
-- Выполнить: mysql -u USER -p DBNAME < database/reset_for_hosting.sql
-- Перед этим очистите файлы: php scripts/clear_uploads.php

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE evaluations;
TRUNCATE TABLE submission_attachments;
TRUNCATE TABLE submission_assigned_experts;
TRUNCATE TABLE submissions;
TRUNCATE TABLE criteria;
TRUNCATE TABLE criterion_groups;
TRUNCATE TABLE stage_attachment_fields;
TRUNCATE TABLE stage_experts;
TRUNCATE TABLE stages;
TRUNCATE TABLE awards;
TRUNCATE TABLE contest_participants;
TRUNCATE TABLE participant_attachments;
TRUNCATE TABLE user_consents;
TRUNCATE TABLE contest_attachment_fields;
TRUNCATE TABLE contest_moderators;
TRUNCATE TABLE contest_expert_pool;
TRUNCATE TABLE contests;
TRUNCATE TABLE contest_series;
TRUNCATE TABLE consent_documents;
TRUNCATE TABLE site_announcements;
TRUNCATE TABLE in_app_notifications;
TRUNCATE TABLE user_roles;
TRUNCATE TABLE users;
TRUNCATE TABLE system_roles;

SET FOREIGN_KEY_CHECKS = 1;

-- Базовые роли (как в seed.sql)
INSERT INTO system_roles (id, slug, name) VALUES
  (1, 'admin', 'Администратор'),
  (2, 'moderator', 'Модератор'),
  (3, 'expert', 'Эксперт'),
  (4, 'participant', 'Участник');

-- Администратор: admin@iro.local / password (смените после первого входа)
INSERT INTO users (id, email, password_hash, first_name, last_name, is_active, is_staff, is_superuser)
VALUES (
  1,
  'admin@iro.local',
  '$2y$12$T5ZYYhmj94x94J0e2KzT0e5CAKpC6zsT00bMu45vsLbCbZCf8IFWq',
  'Админ',
  'Сайта',
  1,
  1,
  1
);

INSERT INTO user_roles (user_id, role_id) VALUES (1, 1);
