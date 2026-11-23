-- Удаление дубликатов contest_participants (один пользователь — одна строка на конкурс)
-- и уникальный ключ (user_id, contest_id). Награды ссылаются на participant_id — переносим на «выжившую» строку.

START TRANSACTION;

CREATE TABLE _migration_cp_survivor (
    user_id BIGINT UNSIGNED NOT NULL,
    contest_id BIGINT UNSIGNED NOT NULL,
    survivor_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, contest_id)
);

INSERT INTO _migration_cp_survivor (user_id, contest_id, survivor_id)
SELECT
    user_id,
    contest_id,
    CAST(
        SUBSTRING_INDEX(
            GROUP_CONCAT(id ORDER BY admitted_max_stage_order DESC, id ASC SEPARATOR ','),
            ',',
            1
        ) AS UNSIGNED
    ) AS survivor_id
FROM contest_participants
GROUP BY user_id, contest_id;

-- Перевесить награды на id выжившей строки
UPDATE awards a
INNER JOIN contest_participants cp ON cp.id = a.participant_id AND cp.contest_id = a.contest_id
INNER JOIN _migration_cp_survivor s ON s.user_id = cp.user_id AND s.contest_id = cp.contest_id
SET a.participant_id = s.survivor_id
WHERE cp.id <> s.survivor_id;

-- Если у двух дубликатов были две записи awards — оставляем одну (меньший id)
DELETE a1
FROM awards a1
INNER JOIN awards a2
    ON a1.contest_id = a2.contest_id
    AND a1.participant_id = a2.participant_id
    AND a1.id > a2.id;

-- Удалить лишние строки участников
DELETE cp
FROM contest_participants cp
INNER JOIN _migration_cp_survivor s ON s.user_id = cp.user_id AND s.contest_id = cp.contest_id
WHERE cp.id <> s.survivor_id;

DROP TABLE _migration_cp_survivor;

-- Старый индекс дублирует уникальный ключ
ALTER TABLE contest_participants
    DROP INDEX idx_contest_participants_user_contest;

ALTER TABLE contest_participants
    ADD UNIQUE KEY uk_contest_participants_user_contest (user_id, contest_id);

COMMIT;
