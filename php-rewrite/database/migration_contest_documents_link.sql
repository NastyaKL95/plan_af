-- Ссылка на шаблоны, приказы и др. (вместе с файлом положения в блоке «Документы»).
-- Запуск: mysql -u USER -p DBNAME < database/migration_contest_documents_link.sql

SET NAMES utf8mb4;

ALTER TABLE contests
    ADD COLUMN documents_link VARCHAR(500) NOT NULL DEFAULT '' AFTER regulations_path;
