-- Формат этапа: 0 — онлайн (участники подают работы в системе), 1 — очный (подачи нет, оценивание экспертами в системе).

ALTER TABLE stages
    ADD COLUMN stage_format TINYINT(1) NOT NULL DEFAULT 0
    AFTER phase_status;
