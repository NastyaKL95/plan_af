-- Партнёры на главной (логотип, ссылка, порядок). Выполнить один раз на существующей БД.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS site_partners (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    website_url VARCHAR(500) NOT NULL DEFAULT '',
    logo_path VARCHAR(500) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMIT;
