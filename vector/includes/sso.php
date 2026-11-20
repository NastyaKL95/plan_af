<?php
declare(strict_types=1);

require_once __DIR__ . '/sso_token.php';

/**
 * Общий вход с «Аудиторным фондом».
 * Сайты на разных доменах, поэтому cookie сессии не общая: браузер один раз
 * переходит на соседний сайт, тот подписывает короткий билет, и мы открываем свою сессию.
 * Подключать после bootstrap.php.
 */

function planner_sso_defaults(): array
{
    return [
        'enabled' => true,
        'secret' => 'af5f7b16f09a100d8e3588020c3f3ed198a559afffb92c2b7a740df8fb5feea6',
        'self_consume_url' => 'https://planiro75.ru/public/sso/consume.php',
        'peer_check_url' => 'https://af.zabedu.ru/sso_check.php',
        'peer_consume_url' => 'https://af.zabedu.ru/sso_consume.php',
        'peer_logout_url' => 'https://af.zabedu.ru/sso_logout.php',
        'logout_return_url' => 'https://planiro75.ru/public/login.php',
        'logout_accept_return' => 'https://af.zabedu.ru/index.php',
    ];
}

function planner_sso_cfg(): array
{
    $sso = app_config()['sso'] ?? [];
    if (!is_array($sso)) {
        $sso = [];
    }
    foreach (planner_sso_defaults() as $key => $value) {
        if (!array_key_exists($key, $sso) || $sso[$key] === '' || $sso[$key] === null) {
            $sso[$key] = $value;
        }
    }

    return $sso;
}

function planner_sso_expire_session_cookie(): void
{
    if (!ini_get('session.use_cookies')) {
        return;
    }
    $params = session_get_cookie_params();
    $sameSite = (string) ($params['samesite'] ?? 'Lax');
    if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
        $sameSite = 'Lax';
    }
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => ($params['path'] ?? '') !== '' ? (string) $params['path'] : '/',
        'domain' => (string) ($params['domain'] ?? ''),
        'secure' => !empty($params['secure']),
        'httponly' => !isset($params['httponly']) || !empty($params['httponly']),
        'samesite' => $sameSite,
    ]);
}

function planner_sso_secret(): string
{
    return trim((string) (planner_sso_cfg()['secret'] ?? ''));
}

function planner_sso_ready(): bool
{
    $cfg = planner_sso_cfg();
    if (empty($cfg['enabled']) || strlen(planner_sso_secret()) < 32) {
        return false;
    }
    foreach (['self_consume_url', 'peer_check_url', 'peer_consume_url'] as $key) {
        if (preg_match('#^https://#', trim((string) ($cfg[$key] ?? ''))) !== 1) {
            return false;
        }
    }

    return true;
}

function planner_sso_https_url(string $value): string
{
    $value = trim($value);

    return preg_match('#^https://#', $value) === 1 ? $value : '';
}

/** Уже идём проверять соседний сайт — повторно с этой страницы не уходим. */
function planner_sso_begin_peer_check(): void
{
    if (!planner_sso_ready()) {
        return;
    }
    $return = planner_sso_https_url((string) (planner_sso_cfg()['self_consume_url'] ?? ''));
    $check = planner_sso_https_url((string) (planner_sso_cfg()['peer_check_url'] ?? ''));
    if ($return === '' || $check === '') {
        return;
    }
    $state = ecosystem_sso_random_state();
    $_SESSION['sso_peer_state'] = $state;
    $_SESSION['sso_peer_state_ts'] = time();
    $sep = str_contains($check, '?') ? '&' : '?';
    header('Location: ' . $check . $sep . http_build_query([
        'state' => $state,
        'return' => $return,
    ], '', '&', PHP_QUERY_RFC3986), true, 302);
    exit;
}

/**
 * @return array{status:string,email?:string,name?:string,provider?:string,subject?:string}
 */
function planner_sso_current_identity(): array
{
    if (empty($_SESSION['user_id'])) {
        return ['status' => 'none'];
    }
    $user = current_user();
    if (!$user) {
        return ['status' => 'none'];
    }
    if (planner_login_block_message($user) !== null) {
        return ['status' => 'denied'];
    }
    $email = strtolower(trim((string) ($user['email'] ?? '')));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return ['status' => 'none'];
    }
    $provider = '';
    $subject = '';
    if (planner_table_has_column('users', 'oauth_provider') && planner_table_has_column('users', 'oauth_subject')) {
        $st = db()->prepare('SELECT oauth_provider, oauth_subject FROM users WHERE id = ? LIMIT 1');
        $st->execute([(int) $user['id']]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $rawProvider = trim((string) ($row['oauth_provider'] ?? ''));
            if ($rawProvider === 'yandex') {
                $provider = 'yandex';
                $subject = trim((string) ($row['oauth_subject'] ?? ''));
            }
        }
    }

    return [
        'status' => 'ok',
        'email' => $email,
        'name' => trim((string) ($user['name'] ?? '')),
        'provider' => $provider,
        'subject' => $subject,
    ];
}

function planner_sso_issue_login_token(array $identity, string $state): string
{
    return ecosystem_sso_token_sign([
        'v' => 1,
        'purpose' => 'login',
        'iss' => 'vector',
        'aud' => 'af',
        'iat' => time(),
        'exp' => time() + 120,
        'jti' => bin2hex(random_bytes(16)),
        'state' => $state,
        'email' => (string) $identity['email'],
        'name' => (string) ($identity['name'] ?? ''),
        'provider' => (string) ($identity['provider'] ?? ''),
        'subject' => (string) ($identity['subject'] ?? ''),
    ], planner_sso_secret());
}

function planner_sso_issue_logout_token(): string
{
    return ecosystem_sso_token_sign([
        'v' => 1,
        'purpose' => 'logout',
        'iss' => 'vector',
        'aud' => 'af',
        'iat' => time(),
        'exp' => time() + 120,
        'jti' => bin2hex(random_bytes(16)),
    ], planner_sso_secret());
}

/**
 * Ошибка входа через Яндекс. Если кнопку нажали в аудиторном фонде, вернуть человека туда, а не оставлять на Векторе.
 */
function planner_sso_oauth_fail(string $error = '', string $notice = ''): void
{
    $relay = (string) ($_SESSION['oauth_yandex_relay'] ?? '');
    $state = (string) ($_SESSION['oauth_yandex_sso_state'] ?? '');
    unset($_SESSION['oauth_yandex_relay'], $_SESSION['oauth_yandex_sso_state']);
    $consume = planner_sso_https_url((string) (planner_sso_cfg()['peer_consume_url'] ?? ''));
    if (
        $relay === 'af'
        && $consume !== ''
        && planner_sso_ready()
        && preg_match('/^[a-f0-9]{32,64}$/', $state) === 1
    ) {
        $code = $error !== '' ? $error : 'sso_denied';
        if (preg_match('/^[a-z0-9_]{1,64}$/', $code) === 1) {
            header('Cache-Control: no-store');
            header('Location: ' . $consume . '?' . http_build_query([
                'sso' => 'error',
                'state' => $state,
                'code' => $code,
            ], '', '&', PHP_QUERY_RFC3986), true, 302);
            exit;
        }
    }
    if ($notice !== '') {
        $_SESSION['login_notice'] = $notice;
    }
    $path = '/login.php' . ($error !== '' ? '?error=' . rawurlencode($error) : '');
    header('Location: ' . planner_public_site_url($path), true, 302);
    exit;
}

/** После входа через Яндекс на Векторе сразу открыть сессию аудиторного фонда, если кнопка была нажата там. */
function planner_sso_finish_redirect(): void
{
    $relay = (string) ($_SESSION['oauth_yandex_relay'] ?? '');
    $state = (string) ($_SESSION['oauth_yandex_sso_state'] ?? '');
    unset($_SESSION['oauth_yandex_relay'], $_SESSION['oauth_yandex_sso_state']);
    if ($relay === 'af' && preg_match('/^[a-f0-9]{32,64}$/', $state) === 1 && planner_sso_redirect_peer_with_ticket($state)) {
        exit;
    }
    header('Location: ' . planner_public_site_url('/index.php'), true, 302);
    exit;
}

function planner_sso_redirect_peer_with_ticket(string $state): bool
{
    if (!planner_sso_ready() || preg_match('/^[a-f0-9]{32,64}$/', $state) !== 1) {
        return false;
    }
    $identity = planner_sso_current_identity();
    $consume = planner_sso_https_url((string) (planner_sso_cfg()['peer_consume_url'] ?? ''));
    if (($identity['status'] ?? '') !== 'ok' || $consume === '') {
        return false;
    }
    $token = planner_sso_issue_login_token($identity, $state);
    header('Cache-Control: no-store');
    header('Location: ' . $consume . '?' . http_build_query([
        'ticket' => $token,
        'state' => $state,
    ], '', '&', PHP_QUERY_RFC3986), true, 302);

    return true;
}

function planner_sso_expected_state(): ?string
{
    $state = (string) ($_SESSION['sso_peer_state'] ?? '');
    $ts = (int) ($_SESSION['sso_peer_state_ts'] ?? 0);
    unset($_SESSION['sso_peer_state'], $_SESSION['sso_peer_state_ts']);
    if ($state === '' || preg_match('/^[a-f0-9]{32,64}$/', $state) !== 1 || (time() - $ts) > 600) {
        return null;
    }

    return $state;
}

function planner_sso_start_session(int $userId): void
{
    if (function_exists('session_regenerate_id')) {
        @session_regenerate_id(true);
    }
    $_SESSION['user_id'] = $userId;
    planner_csrf_rotate();
}

/**
 * Принять личность из аудиторного фонда.
 *
 * @param array<string, mixed> $profile
 * @return array{ok:bool,error?:string}
 */
function planner_sso_accept_identity(array $profile): array
{
    $email = strtolower(trim((string) ($profile['email'] ?? '')));
    $name = trim((string) ($profile['name'] ?? ''));
    if ($name === '') {
        $name = 'Пользователь';
    }
    $provider = (string) ($profile['provider'] ?? '') === 'yandex' ? 'yandex' : '';
    $subject = $provider === 'yandex' ? trim((string) ($profile['subject'] ?? '')) : '';
    if ($provider === 'yandex' && !planner_registration_email_allowed($email)) {
        return ['ok' => false, 'error' => 'sso_email_domain'];
    }
    $hasOauth = planner_table_has_column('users', 'oauth_provider') && planner_table_has_column('users', 'oauth_subject');
    $cols = 'id, organization_id, email, name, role'
        . (planner_table_has_column('users', 'department_id') ? ', department_id' : '')
        . (planner_table_has_column('users', 'account_status') ? ', account_status' : '')
        . (planner_table_has_column('users', 'email_verified_at') ? ', email_verified_at' : '')
        . ($hasOauth ? ', oauth_provider, oauth_subject' : '');

    try {
        $user = null;
        if ($hasOauth && $subject !== '') {
            $st = db()->prepare('SELECT ' . $cols . ' FROM users WHERE oauth_provider = ? AND oauth_subject = ? LIMIT 1');
            $st->execute(['yandex', $subject]);
            $user = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if ($user === null) {
            $st = db()->prepare('SELECT ' . $cols . ' FROM users WHERE LOWER(email) = ? LIMIT 1');
            $st->execute([$email]);
            $user = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($user && $hasOauth && $provider === 'yandex' && $subject !== '') {
                $currProvider = trim((string) ($user['oauth_provider'] ?? ''));
                $currSubject = trim((string) ($user['oauth_subject'] ?? ''));
                $canLink = $currProvider === '' || ($currProvider === 'yandex' && ($currSubject === '' || $currSubject === $subject));
                if ($canLink && ($currProvider !== 'yandex' || $currSubject !== $subject)) {
                    $up = db()->prepare('UPDATE users SET oauth_provider = ?, oauth_subject = ? WHERE id = ?');
                    $up->execute(['yandex', $subject, (int) $user['id']]);
                }
            }
        }
        if ($user) {
            $block = planner_login_block_message($user);
            if ($block !== null) {
                $status = planner_registration_schema_ready() ? (string) ($user['account_status'] ?? '') : '';
                if ($status === 'pending_approval' || $status === 'pending_email') {
                    return ['ok' => false, 'error' => 'sso_pending'];
                }

                return ['ok' => false, 'error' => 'sso_denied'];
            }
            planner_sso_start_session((int) $user['id']);
            try {
                log_activity(
                    (int) $user['organization_id'],
                    (int) $user['id'],
                    'sso_login',
                    'user',
                    (int) $user['id'],
                    'Вход общим сеансом из Аудиторного фонда'
                );
            } catch (Throwable) {
            }

            return ['ok' => true];
        }

        // Пользователи аудиторного фонда уже есть в Векторе. Новую учётку отсюда не создаём:
        // ищем существующую по идентификатору Яндекса или по той же почте.
        return ['ok' => false, 'error' => 'sso_no_account'];
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        return ['ok' => false, 'error' => 'sso_bad'];
    }
}
