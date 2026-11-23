-- Архив конкурса: флаги и путь к сохранённой выгрузке Excel.
-- Выполнить на существующей БД до использования /admin/contests/archive.

START TRANSACTION;

ALTER TABLE contests
    ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN archive_report_path VARCHAR(500) NULL;

COMMIT;
