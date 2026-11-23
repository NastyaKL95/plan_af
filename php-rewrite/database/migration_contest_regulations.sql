-- Файл положения конкурса (PDF, DOC и т.п.).
ALTER TABLE contests
    ADD COLUMN regulations_path VARCHAR(500) NULL AFTER image_path;
