START TRANSACTION;

-- System roles (match Django slugs)
INSERT INTO system_roles (id, slug, name) VALUES
  (1, 'admin', 'Администратор'),
  (2, 'moderator', 'Модератор'),
  (3, 'expert', 'Эксперт'),
  (4, 'participant', 'Участник')
ON DUPLICATE KEY UPDATE slug = VALUES(slug), name = VALUES(name);

-- Admin user
-- Login: admin@iro.local
-- Password: password
-- bcrypt ($2y$), проверяется в PHP через password_verify()
INSERT INTO users (id, email, password_hash, first_name, last_name, is_active, is_staff, is_superuser)
VALUES (
  1,
  'admin@iro.local',
  '$2y$10$/KR0NLvMMu3lxjGmk.zUbOYLk8iYkhCOJIfdaXs0.bpy8H5ftfQt2',
  'Админ',
  'Сайта',
  1,
  1,
  1
)
ON DUPLICATE KEY UPDATE
  email = VALUES(email),
  password_hash = VALUES(password_hash),
  is_active = VALUES(is_active),
  is_staff = VALUES(is_staff),
  is_superuser = VALUES(is_superuser);

INSERT INTO user_roles (user_id, role_id)
VALUES (1, 1)
ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), role_id = VALUES(role_id);

INSERT INTO contests (id, title, start_date, end_date, description, status)
VALUES
  (2, 'Демо-конкурс', '2026-01-10', '2026-12-31', 'Тестовые данные для локального запуска.', 1);

INSERT INTO stages (id, contest_id, title, description, sort_order, start_date, end_date)
VALUES
  (3, 2, 'Этап 1', 'Первый этап (демо).', 1, '2026-01-10', '2026-06-30'),
  (4, 2, 'Этап 2', 'Второй этап (демо).', 2, '2026-07-01', '2026-12-31');

COMMIT;

