<?php

declare(strict_types=1);

/** Конфиг Яндекс OAuth из app.oauth.yandex / oauth.yandex. */
function iro_yandex_oauth_config(?array $config = null): array
{
    $config ??= ($GLOBALS['config'] ?? []);
    $oauth = $config['oauth']['yandex'] ?? null;
    if (!is_array($oauth)) {
        $oauth = [];
    }

    return $oauth;
}

function iro_yandex_oauth_enabled(?array $config = null): bool
{
    $oauth = iro_yandex_oauth_config($config);
    $clientId = trim((string) ($oauth['client_id'] ?? ''));
    $clientSecret = trim((string) ($oauth['client_secret'] ?? ''));
    $redirectUri = trim((string) ($oauth['redirect_uri'] ?? ''));

    return !empty($oauth['enabled'])
        && $clientId !== ''
        && $clientSecret !== ''
        && $redirectUri !== '';
}

/**
 * @return array{status:int,body:string}|null
 */
function iro_yandex_http_post_form(string $url, array $fields): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&', PHP_QUERY_RFC3986));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        return ['status' => $code, 'body' => $raw];
    }

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ];
    $ctx = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $ctx);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    return ['status' => 200, 'body' => $raw];
}

/**
 * @return array{status:int,body:string}|null
 */
function iro_yandex_http_get_json(string $url, string $bearer): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: OAuth ' . $bearer]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        return ['status' => $code, 'body' => $raw];
    }

    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: OAuth {$bearer}\r\n",
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ];
    $ctx = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $ctx);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    return ['status' => 200, 'body' => $raw];
}

/**
 * Вход или регистрация по профилю Яндекса.
 * Без ограничения домена email — сайт открытый для участников.
 *
 * @return array{ok:bool,error?:string,user_id?:int}
 */
function iro_yandex_login_or_register(PDO $pdo, array $profile): array
{
    if (!iro_users_has_oauth_columns($pdo)) {
        return ['ok' => false, 'error' => 'yandex_schema_missing'];
    }

    $subject = trim((string) ($profile['id'] ?? $profile['sub'] ?? ''));
    $email = mb_strtolower(trim((string) ($profile['default_email'] ?? '')));
    $first = trim((string) ($profile['first_name'] ?? ''));
    $last = trim((string) ($profile['last_name'] ?? ''));
    if ($first === '' && $last === '') {
        $real = trim((string) ($profile['real_name'] ?? ''));
        if ($real !== '') {
            $parts = preg_split('/\s+/u', $real, 2) ?: [];
            $first = trim((string) ($parts[0] ?? ''));
            $last = trim((string) ($parts[1] ?? ''));
        }
    }

    if ($subject === '') {
        return ['ok' => false, 'error' => 'yandex_subject_missing'];
    }

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare(
            'SELECT id, is_active FROM users WHERE oauth_provider = :p AND oauth_subject = :s LIMIT 1'
        );
        $st->execute(['p' => 'yandex', 's' => $subject]);
        $byOauth = $st->fetch(PDO::FETCH_ASSOC);
        if ($byOauth) {
            if ((int) ($byOauth['is_active'] ?? 0) !== 1) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'inactive'];
            }
            $userId = (int) $byOauth['id'];
            $pdo->commit();

            return ['ok' => true, 'user_id' => $userId];
        }

        $existingByEmail = null;
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $st = $pdo->prepare(
                'SELECT id, is_active, oauth_provider, oauth_subject FROM users WHERE LOWER(TRIM(email)) = :email LIMIT 1'
            );
            $st->execute(['email' => $email]);
            $existingByEmail = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($existingByEmail) {
            if ((int) ($existingByEmail['is_active'] ?? 0) !== 1) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'inactive'];
            }
            $userId = (int) $existingByEmail['id'];
            $currProvider = trim((string) ($existingByEmail['oauth_provider'] ?? ''));
            $currSubject = trim((string) ($existingByEmail['oauth_subject'] ?? ''));
            if ($currProvider !== '' && ($currProvider !== 'yandex' || ($currSubject !== '' && $currSubject !== $subject))) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'yandex_oauth_conflict'];
            }
            $sqlUpd = 'UPDATE users SET oauth_provider = :p, oauth_subject = :s, updated_at = CURRENT_TIMESTAMP';
            $params = ['p' => 'yandex', 's' => $subject, 'id' => $userId];
            if ($first !== '') {
                $sqlUpd .= ', first_name = :fn';
                $params['fn'] = $first;
            }
            if ($last !== '') {
                $sqlUpd .= ', last_name = :ln';
                $params['ln'] = $last;
            }
            $sqlUpd .= ' WHERE id = :id';
            $pdo->prepare($sqlUpd)->execute($params);
            $pdo->commit();

            return ['ok' => true, 'user_id' => $userId];
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pdo->rollBack();

            return ['ok' => false, 'error' => 'yandex_email_required'];
        }

        $passwordStub = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
        if ($passwordStub === false) {
            $pdo->rollBack();

            return ['ok' => false, 'error' => 'yandex_login_failed'];
        }

        $pdo->prepare(
            'INSERT INTO users (email, password_hash, oauth_provider, oauth_subject, first_name, last_name, middle_name, is_active, is_staff, is_superuser)
             VALUES (:email, :password_hash, :oauth_provider, :oauth_subject, :first_name, :last_name, NULL, 1, 0, 0)'
        )->execute([
            'email' => $email,
            'password_hash' => $passwordStub,
            'oauth_provider' => 'yandex',
            'oauth_subject' => $subject,
            'first_name' => $first !== '' ? $first : null,
            'last_name' => $last !== '' ? $last : null,
        ]);
        $userId = (int) $pdo->lastInsertId();
        $pdo->commit();

        return ['ok' => true, 'user_id' => $userId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        iro_log_line('ERROR', 'yandex oauth login failed', ['error' => $e->getMessage()]);

        return ['ok' => false, 'error' => 'yandex_login_failed'];
    }
}
