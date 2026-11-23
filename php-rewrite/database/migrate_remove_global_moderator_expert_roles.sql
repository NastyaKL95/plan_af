-- Снять глобальные роли «модератор», «эксперт» и «участник» у всех пользователей.
-- В конкурсе: модераторы — contest_moderators; эксперты — пул/этапы/назначения; участники — contest_participants.
-- Запуск (пример): mysql -u USER -p DBNAME < database/migrate_remove_global_moderator_expert_roles.sql
--
-- Строки в system_roles не удаляются (справочник); убираются только связи user_roles.

SET NAMES utf8mb4;

DELETE ur FROM user_roles AS ur
INNER JOIN system_roles AS sr ON sr.id = ur.role_id
WHERE sr.slug IN ('moderator', 'expert', 'participant');
