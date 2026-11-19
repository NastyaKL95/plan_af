<?php
declare(strict_types=1);

require_once __DIR__ . '/sso_token.php';

/**
 * Общий вход с «Вектором».
 * Домены разные, общая cookie невозможна: страница входа один раз спрашивает соседний сайт.
 * Подключать после auth.php.
 */

function audit_fund_sso_secret(): string
{
    return trim((string) (audit_fund_db_config()['sso_secret'] ?? ''));
}

function audit_fund_sso_ready(): bool
{
    $cfg = audit_fund_db_config();
    if (empty($cfg['sso_enabled']) || strlen(audit_fund_sso_secret()) < 32) {
        return false;
    }
    foreach (['sso_self_consume_url', 'sso_peer_check_url', 'sso_peer_consume_url'] as $key) {
        if (preg_match('#^https://#', trim((string) ($cfg[$key] ?? ''))) !== 1) {
            return false;
        }
    }

    return true;
}

function audit_fund_sso_https_url(string $value): string
{
    $value = trim($value);

    return preg_match('#^https://#', $value) === 1 ? $value : '';
}

function audit_fund_sso_should_autostart(): bool
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        return false;
    }
    foreach (['sso', 'error', 'registered', 'installed', 'account_disabled'] as $key) {
        if (isset($_GET[$key]) && trim((string) $_GET[$key]) !== '') {
            return false;
        }
    }
    audit_fund_session_start();
    $flash = $_SESSION['audit_fund_page_flash']['index.php'] ?? null;
    if (is_array($flash) && $flash !== []) {
        return false;
    }

    return audit_fund_sso_ready();
}

function audit_fund_sso_begin_peer_check(): void
{
    if (!audit_fund_sso_ready()) {
        return;
    }
    $return = audit_fund_sso_https_url((string) (audit_fund_db_config()['sso_self_consume_url'] ?? ''));
    $check = audit_fund_sso_https_url((string) (audit_fund_db_config()['sso_peer_check_url'] ?? ''));
    if ($return === '' || $check === '') {
        return;
    }
    audit_fund_session_start();
    $state = ecosystem_sso_random_state();
    $_SESSION['audit_fund_sso_state'] = $state;
    $_SESSION['audit_fund_sso_state_ts'] = time();
    $sep = str_contains($check, '?') ? '&' : '?';
    header('Location: ' . $check . $sep . http_build_query([
        'state' => $state,
        'return' => $return,
    ], '', '&', PHP_QUERY_RFC3986), true, 302);
    exit;
}

function audit_fund_sso_expected_state(): ?string
{
    audit_fund_session_start();
    $state = (string) ($_SESSION['audit_fund_sso_state'] ?? '');
    $ts = (int) ($_SESSION['audit_fund_sso_state_ts'] ?? 0);
    unset($_SESSION['audit_fund_sso_state'], $_SESSION['audit_fund_sso_state_ts']);
    if ($state === '' || preg_match('/^[a-f0-9]{32,64}$/', $state) !== 1 || (time() - $ts) > 600) {
        return null;
    }

    return $state;
}

/**
 * @return array{status:string,email?:string,name?:string,provider?:string,subject?:string}
 */
function audit_fund_sso_current_identity(): array
{
    if (!audit_fund_is_logged_in()) {
        return ['status' => 'none'];
    }
    $userId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : 0;
    if ($userId <= 0) {
        return ['status' => 'none'];
    }
    try {
        $pdo = audit_fund_pdo();
        audit_fund_users_ensure_oauth_columns($pdo);
        $hasOauth = audit_fund_users_has_column($pdo, 'oauth_provider') && audit_fund_users_has_column($pdo, 'oauth_subject');
        $sql = 'SELECT id, username, display_name, email, is_active'
            . ($hasOauth ? ', oauth_provider, oauth_subject' : '')
            . ' FROM users WHERE id = ? LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'sso current_identity');

        return ['status' => 'none'];
    }
    if ($row === false) {
        return ['status' => 'none'];
    }
    if ((int) ($row['is_active'] ?? 0) !== 1) {
        return ['status' => 'denied'];
    }
    $email = strtolower(trim((string) ($row['email'] ?? '')));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return ['status' => 'none'];
    }
    $provider = '';
    $subject = '';
    if ($hasOauth && trim((string) ($row['oauth_provider'] ?? '')) === 'yandex') {
        $provider = 'yandex';
        $subject = trim((string) ($row['oauth_subject'] ?? ''));
    }
    $name = trim((string) ($row['display_name'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($row['username'] ?? ''));
    }

    return [
        'status' => 'ok',
        'email' => $email,
        'name' => $name,
        'provider' => $provider,
        'subject' => $subject,
    ];
}

function audit_fund_sso_issue_login_token(array $identity, string $state): string
{
    return ecosystem_sso_token_sign([
        'v' => 1,
        'purpose' => 'login',
        'iss' => 'af',
        'aud' => 'vector',
        'iat' => time(),
        'exp' => time() + 120,
        'jti' => bin2hex(random_bytes(16)),
        'state' => $state,
        'email' => (string) $identity['email'],
        'name' => (string) ($identity['name'] ?? ''),
        'provider' => (string) ($identity['provider'] ?? ''),
        'subject' => (string) ($identity['subject'] ?? ''),
    ], audit_fund_sso_secret());
}

function audit_fund_sso_issue_logout_token(): string
{
    return ecosystem_sso_token_sign([
        'v' => 1,
        'purpose' => 'logout',
        'iss' => 'af',
        'aud' => 'vector',
        'iat' => time(),
        'exp' => time() + 120,
        'jti' => bin2hex(random_bytes(16)),
    ], audit_fund_sso_secret());
}

function audit_fund_users_has_column(PDO $pdo, string $column, bool $refresh = false): bool
{
    static $cache = [];
    if (preg_match('/^[a-z_]+$/', $column) !== 1) {
        return false;
    }
    if ($refresh) {
        unset($cache[$column]);
    }
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    $stmt = $pdo->prepare('SHOW COLUMNS FROM users LIKE ?');
    $stmt->execute([$column]);
    $cache[$column] = $stmt->fetch(PDO::FETCH_ASSOC) !== false;

    return $cache[$column];
}

function audit_fund_users_ensure_oauth_columns(PDO $pdo): void
{
    static $done = false;
    if ($done || !audit_fund_runtime_migrations_enabled()) {
        $done = true;

        return;
    }
    $done = true;
    if (!audit_fund_users_has_column($pdo, 'oauth_provider')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN oauth_provider VARCHAR(32) NULL DEFAULT NULL AFTER password_hash');
        audit_fund_users_has_column($pdo, 'oauth_provider', true);
    }
    if (!audit_fund_users_has_column($pdo, 'oauth_subject')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN oauth_subject VARCHAR(191) NULL DEFAULT NULL AFTER oauth_provider');
        audit_fund_users_has_column($pdo, 'oauth_subject', true);
    }
    $idx = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'uk_users_oauth'");
    if ($idx !== false && $idx->fetch(PDO::FETCH_ASSOC) === false) {
        $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uk_users_oauth (oauth_provider, oauth_subject)');
    }
}

function audit_fund_safe_display_name(string $name): ?string
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    if (audit_fund_validate_display_name($name) === null) {
        return $name;
    }
    $cleaned = preg_replace('/[^\p{L}\p{N}\s\'.-]+/u', ' ', $name);
    $cleaned = trim(preg_replace('/\s+/u', ' ', (string) $cleaned) ?? '');
    if ($cleaned === '' || audit_fund_validate_display_name($cleaned) !== null) {
        return null;
    }

    return $cleaned;
}

function audit_fund_username_from_email(PDO $pdo, string $email): string
{
    $local = strtolower((string) strstr($email, '@', true));
    $local = preg_replace('/[^a-z0-9._-]/', '', $local) ?? '';
    if (strlen($local) < 3) {
        $local = 'user';
    }
    $local = substr($local, 0, 40);
    for ($n = 0; $n < 100; $n++) {
        $candidate = $n === 0 ? $local : $local . $n;
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$candidate]);
        if ($stmt->fetchColumn() === false) {
            return $candidate;
        }
    }

    return $local . bin2hex(random_bytes(3));
}

/**
 * Войти или создать пользователя по подтверждённой почте соседнего сайта / Яндекса.
 *
 * @return array{ok:bool,error?:string}
 */
function audit_fund_external_login(string $email, string $name, string $provider, string $subject, bool $provision): array
{
    $email = strtolower(trim($email));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return ['ok' => false, 'error' => 'sso_bad'];
    }
    $provider = $provider === 'yandex' ? 'yandex' : '';
    $subject = $provider === 'yandex' ? trim($subject) : '';
    $displayName = audit_fund_safe_display_name($name);

    try {
        $pdo = audit_fund_pdo();
        audit_fund_users_ensure_role_column($pdo);
        audit_fund_users_ensure_oauth_columns($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'external login schema');

        return ['ok' => false, 'error' => 'sso_bad'];
    }

    $hasOauth = audit_fund_users_has_column($pdo, 'oauth_provider') && audit_fund_users_has_column($pdo, 'oauth_subject');
    $cols = 'id, username, display_name, email, is_active, role' . ($hasOauth ? ', oauth_provider, oauth_subject' : '');

    try {
        $user = null;
        if ($hasOauth && $subject !== '') {
            $stmt = $pdo->prepare('SELECT ' . $cols . ' FROM users WHERE oauth_provider = ? AND oauth_subject = ? LIMIT 1');
            $stmt->execute(['yandex', $subject]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if ($user === null) {
            $stmt = $pdo->prepare('SELECT ' . $cols . ' FROM users WHERE LOWER(email) = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($user && $hasOauth && $provider === 'yandex' && $subject !== '') {
                $currProvider = trim((string) ($user['oauth_provider'] ?? ''));
                $currSubject = trim((string) ($user['oauth_subject'] ?? ''));
                if ($currProvider !== '' && ($currProvider !== 'yandex' || ($currSubject !== '' && $currSubject !== $subject))) {
                    return ['ok' => false, 'error' => 'yandex_oauth_conflict'];
                }
                if ($currProvider !== 'yandex' || $currSubject !== $subject) {
                    $upd = $pdo->prepare('UPDATE users SET oauth_provider = ?, oauth_subject = ? WHERE id = ?');
                    $upd->execute(['yandex', $subject, (int) $user['id']]);
                }
            }
        }
        if ($user) {
            if ((int) ($user['is_active'] ?? 0) !== 1) {
                return ['ok' => false, 'error' => 'inactive'];
            }
            if ($displayName !== null && trim((string) ($user['display_name'] ?? '')) === '') {
                $upd = $pdo->prepare('UPDATE users SET display_name = ? WHERE id = ?');
                $upd->execute([$displayName, (int) $user['id']]);
                $user['display_name'] = $displayName;
            }
            audit_fund_establish_session([
                'id' => (int) $user['id'],
                'username' => (string) $user['username'],
                'display_name' => (string) ($user['display_name'] ?? ''),
                'email' => (string) ($user['email'] ?? $email),
                'role' => (string) ($user['role'] ?? 'user'),
            ]);
            require_once __DIR__ . '/audit_log.php';
            audit_fund_log_action('sso_login', 'user', (string) $user['id']);

            return ['ok' => true];
        }
        if (!$provision) {
            return ['ok' => false, 'error' => 'sso_no_account'];
        }
        $username = audit_fund_username_from_email($pdo, $email);
        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        if ($hash === false) {
            return ['ok' => false, 'error' => 'sso_bad'];
        }
        if ($hasOauth) {
            $ins = $pdo->prepare(
                'INSERT INTO users (username, display_name, email, password_hash, is_active, role, oauth_provider, oauth_subject)
                 VALUES (?, ?, ?, ?, 1, "user", ?, ?)'
            );
            $ins->execute([
                $username,
                $displayName,
                $email,
                $hash,
                $provider !== '' ? $provider : null,
                $subject !== '' ? $subject : null,
            ]);
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO users (username, display_name, email, password_hash, is_active, role) VALUES (?, ?, ?, ?, 1, "user")'
            );
            $ins->execute([$username, $displayName, $email, $hash]);
        }
        $userId = (int) $pdo->lastInsertId();
        audit_fund_establish_session([
            'id' => $userId,
            'username' => $username,
            'display_name' => (string) $displayName,
            'email' => $email,
            'role' => 'user',
        ]);
        require_once __DIR__ . '/audit_log.php';
        audit_fund_log_action('sso_login', 'user', (string) $userId, ['created' => true]);

        return ['ok' => true];
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'external login');

        return ['ok' => false, 'error' => 'sso_bad'];
    }
}

function audit_fund_auth_notice(string $code): string
{
    $map = [
        'yandex_not_configured' => 'Вход через Яндекс пока не настроен.',
        'yandex_bad_state' => 'Сессия авторизации Яндекса истекла. Попробуйте снова.',
        'yandex_access_denied' => 'Авторизация через Яндекс отменена.',
        'yandex_no_code' => 'Яндекс не вернул код авторизации.',
        'yandex_token_http' => 'Не удалось получить токен Яндекс.',
        'yandex_token_json' => 'Некорректный ответ токена Яндекс.',
        'yandex_token_missing' => 'Яндекс не вернул ключ доступа.',
        'yandex_profile_http' => 'Не удалось получить профиль Яндекс.',
        'yandex_profile_json' => 'Некорректный ответ профиля Яндекс.',
        'yandex_subject_missing' => 'В профиле Яндекс нет идентификатора пользователя.',
        'yandex_oauth_conflict' => 'Эта почта уже связана с другим входом через Яндекс.',
        'yandex_registration_closed' => 'Регистрация закрыта. Войти через Яндекс можно, если учётная запись уже есть.',
        'yandex_email_missing' => 'Яндекс не передал адрес почты. Разрешите доступ к email и повторите вход.',
        'yandex_pending_approval' => 'Аккаунт в Векторе создан и ждёт активации администратором.',
        'yandex_email_domain_not_allowed' => 'Для входа в Вектор нужна почта на домене iro75.ru.',
        'yandex_login_failed' => 'Не удалось выполнить вход через Яндекс.',
        'maintenance' => 'Сейчас техническое обслуживание. Новый вход временно закрыт.',
        'sso_pending' => 'Аккаунт в Векторе создан и ждёт активации администратором.',
        'sso_bad' => 'Не удалось подтвердить общий вход. Войдите логином и паролем.',
        'sso_denied' => 'Сессия в соседнем сервисе есть, но вход туда сейчас запрещён.',
        'sso_no_account' => 'Учётная запись с этой почтой не найдена.',
        'sso_not_configured' => 'Общий вход с Вектором не настроен.',
        'inactive' => 'Учётная запись отключена. Для доступа обратитесь к администратору.',
    ];

    return $map[$code] ?? '';
}
