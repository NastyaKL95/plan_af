-- Группы критериев на этапе: сначала создаётся группа, затем критерии можно отнести к группе.
-- Запуск: один раз после бэкапа.

START TRANSACTION;

CREATE TABLE criterion_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    stage_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0
);

ALTER TABLE criteria
    ADD COLUMN group_id BIGINT UNSIGNED NULL AFTER stage_id,
    ADD COLUMN sort_order SMALLINT NOT NULL DEFAULT 0 AFTER weight,
    ADD CONSTRAINT fk_criteria_criterion_group
        FOREIGN KEY (group_id) REFERENCES criterion_groups(id) ON DELETE SET NULL;

CREATE INDEX idx_criteria_stage_group_sort ON criteria (stage_id, group_id, sort_order, id);
CREATE INDEX idx_criterion_groups_stage_sort ON criterion_groups (stage_id, sort_order, id);

COMMIT;
