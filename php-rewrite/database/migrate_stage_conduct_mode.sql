-- Добавление режима проведения конкурса и статуса этапа (для существующих установок).
-- stage_conduct_mode: 0 — последовательные этапы (как раньше), 1 — подача на этапы по статусу.
-- phase_status: 1 скоро, 2 приём работ, 3 оценивание, 4 завершён.

ALTER TABLE contests
    ADD COLUMN stage_conduct_mode TINYINT(1) NOT NULL DEFAULT 0
    AFTER require_explicit_stage_admission;

ALTER TABLE stages
    ADD COLUMN phase_status TINYINT NOT NULL DEFAULT 1
    AFTER include_scores_in_final_ranking;
