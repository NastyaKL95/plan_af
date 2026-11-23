-- Индексы под типичные фильтры при росте объёма данных (применить один раз на боевой БД).
-- Если индекс уже есть, команда завершится ошибкой — проверьте SHOW INDEX FROM ...

START TRANSACTION;

ALTER TABLE submissions ADD INDEX idx_submissions_contest_id (contest_id);
ALTER TABLE submissions ADD INDEX idx_submissions_stage_id (stage_id);

-- Выборки оценок по работам конкурса / по submission_id
ALTER TABLE evaluations ADD INDEX idx_evaluations_submission_id (submission_id);

COMMIT;
