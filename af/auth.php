<?php
/**
 * Авторизация «Аудиторный фонд»: сессия и учётные записи в MySQL (таблица users).
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/install_gate.php';
audit_fund_install_redirect_if_needed();

$auditFundVendorAutoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($auditFundVendorAutoload)) {
    require_once $auditFundVendorAutoload;
}
// БД и заголовки безопасности нужны всегда (Composer не заменяет этот слой).
require_once __DIR__ . '/includes/database.php';
audit_fund_apply_production_php_settings();
require_once __DIR__ . '/includes/security_headers.php';

require_once __DIR__ . '/includes/app_log.php';

audit_fund_send_security_headers();

require_once __DIR__ . '/includes/redirect_flash.php';
require_once __DIR__ . '/includes/http_input.php';

const AUDIT_FUND_REMEMBER_COOKIE = 'audit_fund_remember';
const AUDIT_FUND_REMEMBER_DAYS = 30;

function audit_fund_users_ensure_role_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    if (!audit_fund_runtime_migrations_enabled()) {
        $checked = true;

        return;
    }

    $existsStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    $exists = $existsStmt->fetch(PDO::FETCH_ASSOC) !== false;
    if (!$exists) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('admin','manager','user') NOT NULL DEFAULT 'user' AFTER is_active");
    } else {
        $colStmt = $pdo->query("SHOW COLUMNS FROM users WHERE Field = 'role'");
        $col = $colStmt->fetch(PDO::FETCH_ASSOC);
        $type = isset($col['Type']) ? (string) $col['Type'] : '';
        if ($type !== '' && stripos($type, 'manager') === false) {
            $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager','user') NOT NULL DEFAULT 'user'");
        }
    }

    $dnStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'display_name'");
    if ($dnStmt !== false && $dnStmt->fetch(PDO::FETCH_ASSOC) === false) {
        $pdo->exec('ALTER TABLE users ADD COLUMN display_name VARCHAR(191) NULL DEFAULT NULL AFTER username');
    }

    $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($adminCount === 0) {
        $promoteStmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE username = ? LIMIT 1");
        $promoteStmt->execute(['audit_fund']);
        if ($promoteStmt->rowCount() === 0) {
            $pdo->exec("UPDATE users SET role = 'admin' ORDER BY id ASC LIMIT 1");
        }
    }

    $checked = true;
}

/**
 * @return array{id:int,username:string,display_name:?string,email:?string,password_hash:string,role:string}|null
 */
function audit_fund_find_user_by_login(PDO $pdo, string $login): ?array
{
    if ($login === '') {
        return null;
    }

    audit_fund_users_ensure_role_column($pdo);
    $stmt = $pdo->prepare(
        'SELECT id, username, display_name, email, password_hash, role FROM users
         WHERE is_active = 1 AND (username = :login OR email = :login)
         LIMIT 1'
    );
    $stmt->execute(['login' => $login]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'username' => (string) $row['username'],
        'display_name' => trim((string) ($row['display_name'] ?? '')),
        'email' => $row['email'] !== null ? (string) $row['email'] : null,
        'password_hash' => (string) $row['password_hash'],
        'role' => isset($row['role']) ? (string) $row['role'] : 'user',
    ];
}

/**
 * @return array{id:int,username:string,display_name:string,email:?string,role:string}|null учётная запись при успешном входе (без password_hash)
 */
function audit_fund_try_login(string $login, string $password): ?array
{
    try {
        $pdo = audit_fund_pdo();
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'auth try_login pdo');
        return null;
    }

    try {
        $user = audit_fund_find_user_by_login($pdo, $login);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'auth try_login find_user');
        return null;
    }
    if ($user === null || !password_verify($password, $user['password_hash'])) {
        return null;
    }

    unset($user['password_hash']);

    return $user;
}

function audit_fund_display_login(array $user): string
{
    $name = trim((string) ($user['display_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    $username = trim((string) ($user['username'] ?? ''));
    if ($username !== '') {
        return $username;
    }
    $email = isset($user['email']) ? trim((string) $user['email']) : '';

    return $email !== '' ? $email : '';
}

/**
 * @param array{id:int,username?:string,display_name?:string,email?:?string,role?:string} $user
 */
function audit_fund_establish_session(array $user): void
{
    audit_fund_session_start();
    session_regenerate_id(true);
    $_SESSION['audit_fund_auth'] = true;
    $_SESSION['audit_fund_user_id'] = (int) $user['id'];
    $_SESSION['audit_fund_role'] = audit_fund_normalize_role((string) ($user['role'] ?? 'user'));
    $_SESSION['audit_fund_login'] = audit_fund_display_login($user);
}

function audit_fund_normalize_role(string $role): string
{
    if ($role === 'admin') {
        return 'admin';
    }
    if ($role === 'manager') {
        return 'manager';
    }

    return 'user';
}

function audit_fund_role_label(string $role): string
{
    switch (audit_fund_normalize_role($role)) {
        case 'admin':
            return 'Администратор';
        case 'manager':
            return 'Менеджер';
        default:
            return 'Пользователь';
    }
}

/** Полный доступ к данным (как администратор), кроме назначения ролей — только у admin. */
function audit_fund_is_staff(): bool
{
    $r = audit_fund_current_role();

    return $r === 'admin' || $r === 'manager';
}

function audit_fund_require_staff(): void
{
    audit_fund_require_auth();
    if (!audit_fund_is_staff()) {
        header('Location: access_denied.php');
        exit;
    }
}

function audit_fund_clear_remember_cookie(): void
{
    $p = session_get_cookie_params();
    $path = $p['path'] !== '' ? $p['path'] : '/';
    $domain = $p['domain'] ?? '';
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie(AUDIT_FUND_REMEMBER_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => $path,
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function audit_fund_clear_remember_tokens_for_user(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare(
        'UPDATE users SET remember_token_hash = NULL, remember_token_expires_at = NULL WHERE id = ?'
    );
    $stmt->execute([$userId]);
}

function audit_fund_issue_remember_me(PDO $pdo, int $userId): void
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = new DateTimeImmutable('+' . AUDIT_FUND_REMEMBER_DAYS . ' days');

    $stmt = $pdo->prepare(
        'UPDATE users SET remember_token_hash = ?, remember_token_expires_at = ? WHERE id = ?'
    );
    $stmt->execute([$hash, $expires->format('Y-m-d H:i:s'), $userId]);

    $p = session_get_cookie_params();
    $path = $p['path'] !== '' ? $p['path'] : '/';
    $domain = $p['domain'] ?? '';
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    setcookie(AUDIT_FUND_REMEMBER_COOKIE, $token, [
        'expires' => $expires->getTimestamp(),
        'path' => $path,
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function audit_fund_restore_session_from_remember_cookie(): bool
{
    $raw = $_COOKIE[AUDIT_FUND_REMEMBER_COOKIE] ?? null;
    if (!is_string($raw) || strlen($raw) < 64) {
        return false;
    }

    $hash = hash('sha256', $raw);

    try {
        $pdo = audit_fund_pdo();
        audit_fund_users_ensure_role_column($pdo);
        $stmt = $pdo->prepare(
            'SELECT id, username, display_name, email, role FROM users
             WHERE is_active = 1
               AND remember_token_hash = ?
               AND remember_token_expires_at IS NOT NULL
               AND remember_token_expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$hash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'auth restore_session');
        return false;
    }

    if ($user === false) {
        audit_fund_clear_remember_cookie();

        return false;
    }

    audit_fund_establish_session([
        'id' => (int) $user['id'],
        'username' => (string) $user['username'],
        'display_name' => isset($user['display_name']) ? (string) $user['display_name'] : '',
        'email' => $user['email'] !== null ? (string) $user['email'] : null,
        'role' => (string) ($user['role'] ?? 'user'),
    ]);

    return true;
}

function audit_fund_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        audit_fund_configure_session_cookie();
        session_start();
    }
}

function audit_fund_is_logged_in(): bool
{
    audit_fund_session_start();
    if (!empty($_SESSION['audit_fund_auth'])) {
        return true;
    }

    return audit_fund_restore_session_from_remember_cookie();
}

/**
 * Сброс сессии, если учётная запись отключена (is_active = 0) или удалена.
 */
function audit_fund_invalidate_session_if_user_inactive(): void
{
    audit_fund_session_start();
    if (empty($_SESSION['audit_fund_auth'])) {
        return;
    }
    $userId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : 0;
    if ($userId <= 0) {
        return;
    }

    try {
        $pdo = audit_fund_pdo();
        audit_fund_users_ensure_role_column($pdo);
        $stmt = $pdo->prepare('SELECT is_active FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false && (int) ($row['is_active'] ?? 0) === 1) {
            return;
        }
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'auth invalidate_if_inactive');

        return;
    }

    audit_fund_invalidate_remember_for_user_id($userId);
    audit_fund_clear_remember_cookie();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    header('Location: index.php?account_disabled=1');
    exit;
}

/** Если пользователь не вошёл — перенаправление на страницу входа. */
function audit_fund_require_auth(): void
{
    if (!audit_fund_is_logged_in()) {
        header('Location: index.php');
        exit;
    }
    audit_fund_invalidate_session_if_user_inactive();
}

function audit_fund_current_role(): string
{
    audit_fund_session_start();

    if (!empty($_SESSION['audit_fund_auth'])) {
        $userId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : 0;
        if ($userId > 0) {
            static $roleLoadedFromDb = false;
            if (!$roleLoadedFromDb) {
                try {
                    $pdo = audit_fund_pdo();
                    audit_fund_users_ensure_role_column($pdo);
                    $stmt = $pdo->prepare(
                        'SELECT role, username, display_name, email FROM users WHERE id = ? AND is_active = 1 LIMIT 1'
                    );
                    $stmt->execute([$userId]);
                    $uRow = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($uRow !== false) {
                        $dbRole = isset($uRow['role']) ? (string) $uRow['role'] : '';
                        if ($dbRole !== '') {
                            $_SESSION['audit_fund_role'] = audit_fund_normalize_role($dbRole);
                        }
                        $_SESSION['audit_fund_login'] = audit_fund_display_login([
                            'display_name' => isset($uRow['display_name']) ? (string) $uRow['display_name'] : '',
                            'username' => (string) ($uRow['username'] ?? ''),
                            'email' => isset($uRow['email']) && $uRow['email'] !== null ? (string) $uRow['email'] : null,
                        ]);
                    }
                } catch (Throwable $e) {
                    audit_fund_log_exception($e, 'auth current_role');
                    // Если БД временно недоступна, используем роль из сессии.
                }
                $roleLoadedFromDb = true;
            }
        }
    }

    return audit_fund_normalize_role((string) ($_SESSION['audit_fund_role'] ?? 'user'));
}

function audit_fund_is_admin(): bool
{
    return audit_fund_current_role() === 'admin';
}

function audit_fund_require_admin(): void
{
    audit_fund_require_auth();
    if (!audit_fund_is_admin()) {
        header('Location: access_denied.php');
        exit;
    }
}

function audit_fund_csrf_token(): string
{
    audit_fund_session_start();
    $token = isset($_SESSION['audit_fund_csrf']) ? (string) $_SESSION['audit_fund_csrf'] : '';
    if ($token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION['audit_fund_csrf'] = $token;
    }

    return $token;
}

function audit_fund_verify_csrf(?string $token): bool
{
    audit_fund_session_start();
    $expected = isset($_SESSION['audit_fund_csrf']) ? (string) $_SESSION['audit_fund_csrf'] : '';
    if ($expected === '' || $token === null || $token === '') {
        return false;
    }

    return hash_equals($expected, $token);
}

/** Сброс «запомнить меня» в БД (вызывать до уничтожения сессии, если известен user id). */
function audit_fund_invalidate_remember_for_user_id(int $userId): void
{
    try {
        $pdo = audit_fund_pdo();
        audit_fund_clear_remember_tokens_for_user($pdo, $userId);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'auth invalidate_remember');
        // игнорируем при недоступной БД — выход из сессии всё равно выполним
    }
}

/**
 * Проверка отображаемого имени (регистрация, профиль). Код ошибки или null.
 */
function audit_fund_validate_display_name(string $displayNameRaw): ?string
{
    $displayName = trim($displayNameRaw);
    if ($displayName === '') {
        return 'display_name_empty';
    }
    $nameLen = function_exists('mb_strlen') ? mb_strlen($displayName, 'UTF-8') : strlen($displayName);
    if ($nameLen < 1 || $nameLen > 191) {
        return 'display_name_len';
    }
    if (!preg_match('/^[\p{L}\p{N}\s\'.-]+$/u', $displayName)) {
        return 'display_name_chars';
    }

    return null;
}

/**
 * Проверка полей регистрации. Возвращает код ошибки или null, если всё допустимо.
 */
function audit_fund_register_validate(
    string $displayNameRaw,
    string $username,
    string $emailRaw,
    string $password,
    string $passwordConfirm
): ?string {
    $nameErr = audit_fund_validate_display_name($displayNameRaw);
    if ($nameErr !== null) {
        return $nameErr;
    }

    $username = trim($username);
    if (strlen($username) < 3 || strlen($username) > 64) {
        return 'username_len';
    }
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        return 'username_chars';
    }

    $email = trim($emailRaw);
    if ($email !== '') {
        if (strlen($email) > 191 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'email_invalid';
        }
    }

    if (strlen($password) < 8) {
        return 'password_short';
    }
    if (!hash_equals($password, $passwordConfirm)) {
        return 'password_mismatch';
    }

    return null;
}

/**
 * Создание учётной записи. null — успех, иначе код ошибки.
 */
function audit_fund_register_user(string $displayNameRaw, string $username, string $emailRaw, string $password): ?string
{
    $displayName = trim($displayNameRaw);
    $username = trim($username);
    $email = trim($emailRaw);
    $emailDb = $email === '' ? null : $email;

    try {
        $pdo = audit_fund_pdo();
        audit_fund_users_ensure_role_column($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'auth register_user pdo');
        return 'db';
    }

    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() !== false) {
        return 'user_exists';
    }

    if ($emailDb !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$emailDb]);
        if ($stmt->fetchColumn() !== false) {
            return 'email_exists';
        }
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $ins = $pdo->prepare(
            'INSERT INTO users (username, display_name, email, password_hash, is_active, role) VALUES (?, ?, ?, ?, 1, "user")'
        );
        $ins->execute([$username, $displayName !== '' ? $displayName : null, $emailDb, $hash]);
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1062) {
            return 'duplicate';
        }

        audit_fund_log_exception($e, 'auth register_user insert');
        return 'db';
    }

    return null;
}

/**
 * Сохранение отображаемого имени текущего пользователя. Обновляет сессию (audit_fund_login).
 * null — успех, иначе код ошибки.
 */
function audit_fund_user_update_display_name(int $userId, string $displayNameRaw): ?string
{
    if ($userId <= 0) {
        return 'invalid';
    }

    $nameErr = audit_fund_validate_display_name($displayNameRaw);
    if ($nameErr !== null) {
        return $nameErr;
    }

    $displayName = trim($displayNameRaw);

    try {
        $pdo = audit_fund_pdo();
        audit_fund_users_ensure_role_column($pdo);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'auth user_update_display_name pdo');

        return 'db';
    }

    try {
        $upd = $pdo->prepare('UPDATE users SET display_name = ? WHERE id = ? AND is_active = 1');
        $upd->execute([$displayName, $userId]);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'auth user_update_display_name update');

        return 'db';
    }

    $stmt = $pdo->prepare(
        'SELECT username, display_name, email FROM users WHERE id = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return 'not_found';
    }

    audit_fund_session_start();
    $_SESSION['audit_fund_login'] = audit_fund_display_login([
        'display_name' => isset($row['display_name']) ? (string) $row['display_name'] : '',
        'username' => (string) ($row['username'] ?? ''),
        'email' => isset($row['email']) && $row['email'] !== null ? (string) $row['email'] : null,
    ]);

    return null;
}
