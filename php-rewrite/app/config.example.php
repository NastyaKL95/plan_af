<?php

return [
    'db' => [
        'host' => 'MySQL-8.4',
        'port' => 3306,
        'dbname' => 'iro_php',
        'user' => 'root',
        'password' => '',
    ],
    /*
    Вторая MySQL: если локальный пароль не подошёл, проверяется внешняя таблица.
    email_column — колонка идентификатора (часто email или login); сравнение без учёта регистра.
    password_format: php_password (password_hash PHP) или plain (небезопасно, только если наследие так хранит пароли).
    При успехе в локальной users создаётся/обновляется запись; в БД сохраняется уже нормальный password_hash().
    */
    // 'external_auth' => [
    //     'enabled' => true,
    //     'host' => 'MySQL-8.4',
    //     'port' => 3306,
    //     'dbname' => 'other_app',
    //     'user' => 'readonly_user',
    //     'password' => '',
    //     'connect_timeout' => 10,
    //     /* имя таблицы или база.таблица: */
    //     'table' => 'c1_zabedu.dat_peoples',
    //     'email_column' => 'login',
    //     'password_hash_column' => 'passwd',
    //     'password_format' => 'plain',
    //     'first_name_column' => 'first_name',
    //     'last_name_column' => 'last_name',
    //     'login_page_note' => 'Можно войти по логину и паролю из справочника (dat_peoples).',
    // ],
    /*
    Вход через Яндекс (как в Планировщике). Без ограничения домена email —
    подходит для открытого сайта конкурсов. Redirect URI должен совпадать с кабинетом OAuth.
    Нужна миграция database/migration_oauth_yandex_v1.sql (или свежий schema.sql).
    */
    'oauth' => [
        'yandex' => [
            'enabled' => false,
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => 'https://example.ru/auth/yandex/callback',
            'scope' => 'login:email login:info',
        ],
    ],
    /*
    Почта для писем «восстановление пароля». Без enabled=true форма сброса работает,
    но письмо не уйдёт (в лог storage/logs/mail.log). Нужна migration_password_reset_v1.sql.
    */
    'mail' => [
        'enabled' => false,
        'from_email' => 'noreply@example.ru',
        'from_name' => 'Конкурсы',
        'smtp_host' => 'smtp.yandex.ru',
        'smtp_port' => 465,
        'smtp_encryption' => 'ssl', // ssl|tls
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_timeout' => 12,
    ],
    'app' => [
        'name' => 'Конкурсы',
        // Публичный origin без слэша в конце — для ссылок в письмах:
        // 'public_origin' => 'https://konkurs.example.ru',
        // Если сайт открывается как http://localhost/папка/public/... , укажите префикс URL без слэша в начале и конце:
        // 'router_base_path' => 'папка/public',
        /* Ограничение рассылки «новая работа» экспертам в режиме «все эксперты этапа» (защита от тысяч INSERT). */
        // 'expert_notify_max_recipients' => 120,
        /* Максимальный размер загружаемого файла в байтах (по умолчанию 50 МБ; также ограничено upload_max_filesize в php.ini). */
        // 'upload_max_bytes' => 52428800,
        /* Cookie сессии (см. app/session.php). На HTTPS в продакшене включите secure. */
        // 'session_cookie_secure' => true,
        // 'session_cookie_samesite' => 'Lax',
        // 'session_cookie_domain' => '',
        // 'session_cookie_lifetime' => 0,
        /* Файловые логи (JSON-строки в storage/logs). Включите на проде: */
        // 'log_enabled' => true,
        // 'log_dir' => 'storage/logs',
        /* Заголовки безопасности (CSP, X-Frame-Options и др.). Отключить: false */
        // 'security_headers_enabled' => true,
        /* Полная строка Content-Security-Policy вместо значения по умолчанию: */
        // 'security_csp' => "default-src 'self'; ...",
        /* Лимит неудачных попыток входа с одного IP (файлы в storage/cache). */
        'login_rate_limit_enabled' => true,
        // 'login_rate_limit_max' => 15,
        // 'login_rate_limit_window' => 900,
        // 'login_rate_limit_cache_dir' => 'storage/cache',
    ],
];
