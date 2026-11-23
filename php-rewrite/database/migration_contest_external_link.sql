-- Конкурсы-ссылки: анонс на сайте с переходом на внешний ресурс.
ALTER TABLE contests
    ADD COLUMN is_external TINYINT(1) NOT NULL DEFAULT 0 AFTER series_id,
    ADD COLUMN external_url VARCHAR(500) NOT NULL DEFAULT '' AFTER is_external;
