-- Для уже развёрнутой БД по старой схеме: убрать преподавателей и группы из расписания.
-- Выполните вручную в phpMyAdmin, если таблицы teachers / study_groups ещё существуют.

USE `audit_fund`;

ALTER TABLE `schedule_entries`
  DROP FOREIGN KEY `fk_schedule_group`,
  DROP FOREIGN KEY `fk_schedule_teacher`;

ALTER TABLE `schedule_entries`
  DROP COLUMN `study_group_id`,
  DROP COLUMN `teacher_id`;

DROP TABLE IF EXISTS `study_groups`;
DROP TABLE IF EXISTS `teachers`;
