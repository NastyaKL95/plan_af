-- Критерии: минимальный балл и вес в сумме (100 = как раньше, без изменения итогов).
ALTER TABLE criteria
    ADD COLUMN min_points INTEGER NOT NULL DEFAULT 0 AFTER name,
    ADD COLUMN weight INTEGER NOT NULL DEFAULT 100 AFTER max_points;
