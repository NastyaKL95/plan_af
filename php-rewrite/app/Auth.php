<?php

declare(strict_types=1);

final class Auth
{
    /** @var 'bad_credentials'|'inactive'|null */
    private static ?string $loginFailureReason = null;

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /** Причина последней неудачи Auth::login (для сообщения на /login). */
    public static function loginFailureReason(): ?string
    {
        return self::$loginFailureReason;
    }

    public static function establishSession(int $userId): void
    {
        if (function_exists('session_regenerate_id')) {
            @session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $userId;
        unset($_SESSION['iro_auth_fp']);
        if (function_exists('iro_csrf_token')) {
            unset($_SESSION['iro_csrf_token']);
            iro_csrf_token();
        }
    }

    public static function login(PDO $pdo, string $email, string $password): bool
    {
        self::$loginFailureReason = null;
        $email = mb_strtolower(trim($email));
        if ($email === '' || $password === '') {
            self::$loginFailureReason = 'bad_credentials';

            return false;
        }

        if (self::loginLocalUser($pdo, $email, $password)) {
            return true;
        }

        if (self::$loginFailureReason === 'inactive') {
            return false;
        }

        $config = $GLOBALS['config'] ?? [];
        if (!self::externalAuthIsEnabled($config)) {
            if (self::$loginFailureReason === null) {
                self::$loginFailureReason = 'bad_credentials';
            }

            return false;
        }

        try {
            $ok = self::loginViaExternalMysql($pdo, $config, $email, $password);
            if (!$ok && self::$loginFailureReason === null) {
                self::$loginFailureReason = 'bad_credentials';
            }

            return $ok;
        } catch (Throwable) {
            self::$loginFailureReason = 'bad_credentials';

            return false;
        }
    }

    /** Текст подсказки на /login, если включена внешняя MySQL-авторизация. */
    public static function externalAuthLoginNote(array $config): ?string
    {
        if (!self::externalAuthIsEnabled($config)) {
            return null;
        }
        $note = trim((string) (($config['external_auth']['login_page_note'] ?? '')));
        return $note !== '' ? $note : null;
    }

    /** Подпись к полю логина на /login. */
    public static function loginUsernameFieldLabel(array $config): string
    {
        return self::externalAuthIsEnabled($config) ? 'Логин или email' : 'Email';
    }

    /** type="text", если внешний вход — логин может быть не email (иначе браузер режет отправку формы). */
    public static function loginUsernameInputIsText(array $config): bool
    {
        return self::externalAuthIsEnabled($config);
    }

    private static function externalAuthIsEnabled(array $config): bool
    {
        $ea = $config['external_auth'] ?? null;
        return is_array($ea) && !empty($ea['enabled']);
    }

    private static function sqlIdentifier(string $name): ?string
    {
        if ($name === '' || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            return null;
        }

        return $name;
    }

    private static function loginLocalUser(PDO $pdo, string $email, string $password): bool
    {
        $stmt = $pdo->prepare(
            'SELECT id, password_hash, is_active FROM users WHERE LOWER(TRIM(email)) = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        $hash = (string) ($user['password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return false;
        }

        if ((int) ($user['is_active'] ?? 0) !== 1) {
            self::$loginFailureReason = 'inactive';

            return false;
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $upd = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
            $upd->execute([
                'h' => password_hash($password, PASSWORD_DEFAULT),
                'id' => (int) $user['id'],
            ]);
        }

        self::establishSession((int) $user['id']);

        return true;
    }

    private static function loginViaExternalMysql(PDO $pdo, array $config, string $email, string $password): bool
    {
        $extPdo = Database::externalAuthConnection($config);
        if ($extPdo === null) {
            return false;
        }

        $ea = $config['external_auth'];
        $tableRef = Database::externalAuthTableReference((string) ($ea['table'] ?? ''));
        $emailCol = self::sqlIdentifier((string) ($ea['email_column'] ?? 'email'));
        $passCol = self::sqlIdentifier((string) ($ea['password_hash_column'] ?? 'password_hash'));
        if ($tableRef === null || $emailCol === null || $passCol === null) {
            return false;
        }

        $fnCol = isset($ea['first_name_column']) ? self::sqlIdentifier((string) $ea['first_name_column']) : null;
        $lnCol = isset($ea['last_name_column']) ? self::sqlIdentifier((string) $ea['last_name_column']) : null;

        $select = "`{$passCol}` AS ext_password_hash";
        if ($fnCol !== null && $fnCol !== '') {
            $select .= ", `{$fnCol}` AS ext_first_name";
        }
        if ($lnCol !== null && $lnCol !== '') {
            $select .= ", `{$lnCol}` AS ext_last_name";
        }

        $sql = "SELECT {$select} FROM {$tableRef} WHERE LOWER(TRIM(`{$emailCol}`)) = :email LIMIT 1";
        $stmt = $extPdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $extSecret = (string) ($row['ext_password_hash'] ?? '');
        if ($extSecret === '') {
            return false;
        }

        $passFormat = (string) ($ea['password_format'] ?? 'php_password');
        if ($passFormat === 'plain') {
            if (trim($extSecret) !== trim($password)) {
                return false;
            }
        } elseif (!password_verify($password, $extSecret)) {
            return false;
        }

        $first = isset($row['ext_first_name']) ? trim((string) $row['ext_first_name']) : '';
        $last = isset($row['ext_last_name']) ? trim((string) $row['ext_last_name']) : '';

        $newHash = password_hash($password, PASSWORD_DEFAULT);

        $stmtLocal = $pdo->prepare(
            'SELECT id FROM users WHERE LOWER(TRIM(email)) = :email LIMIT 1'
        );
        $stmtLocal->execute(['email' => $email]);
        $local = $stmtLocal->fetch();

        if ($local) {
            $id = (int) $local['id'];
            $activeStmt = $pdo->prepare('SELECT is_active FROM users WHERE id = :id LIMIT 1');
            $activeStmt->execute(['id' => $id]);
            $activeRow = $activeStmt->fetch();
            if ($activeRow && (int) ($activeRow['is_active'] ?? 0) !== 1) {
                self::$loginFailureReason = 'inactive';

                return false;
            }
            $sqlUpd = 'UPDATE users SET password_hash = :h, updated_at = CURRENT_TIMESTAMP';
            $params = ['h' => $newHash, 'id' => $id];
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
        } else {
            $pdo->prepare(
                'INSERT INTO users (email, password_hash, first_name, last_name, middle_name, is_active, is_staff, is_superuser)
                 VALUES (:email, :password_hash, :first_name, :last_name, NULL, 1, 0, 0)'
            )->execute([
                'email' => $email,
                'password_hash' => $newHash,
                'first_name' => $first !== '' ? $first : null,
                'last_name' => $last !== '' ? $last : null,
            ]);
            $id = (int) $pdo->lastInsertId();
        }

        self::establishSession($id);

        return true;
    }

    public static function register(PDO $pdo, array $payload): array
    {
        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $firstName = trim((string) ($payload['first_name'] ?? ''));
        $lastName = trim((string) ($payload['last_name'] ?? ''));
        $middleName = trim((string) ($payload['middle_name'] ?? ''));

        if ($email === '' || $password === '') {
            return [false, 'Email и пароль обязательны'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Некорректный email'];
        }
        if (mb_strlen($password) < 8) {
            return [false, 'Пароль должен быть не короче 8 символов'];
        }

        $exists = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $exists->execute(['email' => $email]);
        if ($exists->fetch()) {
            return [false, 'Пользователь с таким email уже существует'];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (email, password_hash, first_name, last_name, middle_name, is_active, is_staff, is_superuser) 
             VALUES (:email, :password_hash, :first_name, :last_name, :middle_name, 1, 0, 0)'
        );
        $stmt->execute([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'middle_name' => $middleName !== '' ? $middleName : null,
        ]);

        return [true, null];
    }

    public static function user(PDO $pdo): ?array
    {
        $id = self::userId();
        if ($id === null) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        if (!$user) {
            self::logout();

            return null;
        }
        if ((int) ($user['is_active'] ?? 0) !== 1) {
            self::logout();

            return null;
        }
        $fp = self::sessionFingerprint($user);
        if (!isset($_SESSION['iro_auth_fp'])) {
            $_SESSION['iro_auth_fp'] = $fp;
        } elseif (!hash_equals((string) $_SESSION['iro_auth_fp'], $fp)) {
            self::logout();

            return null;
        }

        return $user;
    }

    /** Меняется при смене пароля — остальные сессии пользователя после этого становятся недействительными. */
    private static function sessionFingerprint(array $user): string
    {
        return hash('sha256', (int) $user['id'] . '|' . (string) ($user['password_hash'] ?? ''));
    }

    /** Текущая сессия остаётся действующей после смены собственного пароля. */
    private static function refreshOwnSessionFingerprint(int $userId): void
    {
        if (self::userId() === $userId) {
            unset($_SESSION['iro_auth_fp']);
        }
    }

    /**
     * Создаёт токен сброса пароля. Возвращает plaintext-токен или null.
     */
    public static function createPasswordResetToken(PDO $pdo, int $userId, int $ttlHours = 2): ?string
    {
        if ($userId <= 0 || !iro_password_reset_table_ready($pdo)) {
            return null;
        }
        $ttlHours = max(1, min(48, $ttlHours));
        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable) {
            return null;
        }
        $hash = hash('sha256', $token);
        $expires = (new DateTimeImmutable('now'))
            ->modify('+' . $ttlHours . ' hours')
            ->format('Y-m-d H:i:s');
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :uid OR expires_at < NOW()')
            ->execute(['uid' => $userId]);
        $pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
             VALUES (:uid, :hash, :expires)'
        )->execute([
            'uid' => $userId,
            'hash' => $hash,
            'expires' => $expires,
        ]);

        return $token;
    }

    /**
     * @return array{user_id:int}|null
     */
    public static function findPasswordResetToken(PDO $pdo, string $rawToken): ?array
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '' || !iro_password_reset_table_ready($pdo)) {
            return null;
        }
        $hash = hash('sha256', $rawToken);
        $stmt = $pdo->prepare(
            'SELECT t.user_id
             FROM password_reset_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = :hash
               AND t.expires_at > NOW()
               AND u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return ['user_id' => (int) $row['user_id']];
    }

    public static function consumePasswordResetToken(PDO $pdo, string $rawToken, string $newPassword): ?string
    {
        $found = self::findPasswordResetToken($pdo, $rawToken);
        if ($found === null) {
            return 'Ссылка для сброса пароля недействительна или устарела.';
        }
        if (mb_strlen($newPassword) < 8) {
            return 'Пароль должен быть не короче 8 символов.';
        }
        $userId = $found['user_id'];
        $hash = hash('sha256', trim($rawToken));
        $pdo->prepare(
            'UPDATE users SET password_hash = :h, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        )->execute([
            'h' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $userId,
        ]);
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE token_hash = :hash OR user_id = :uid')
            ->execute(['hash' => $hash, 'uid' => $userId]);

        return null;
    }

    public static function updateProfile(PDO $pdo, int $userId, array $payload): void
    {
        $stmt = $pdo->prepare(
            'UPDATE users
             SET first_name = :first_name, last_name = :last_name, middle_name = :middle_name, school = :school, region = :region, municipality = :municipality
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $userId,
            'first_name' => trim((string) ($payload['first_name'] ?? '')) ?: null,
            'last_name' => trim((string) ($payload['last_name'] ?? '')) ?: null,
            'middle_name' => trim((string) ($payload['middle_name'] ?? '')) ?: null,
            'school' => trim((string) ($payload['school'] ?? '')) ?: null,
            'region' => trim((string) ($payload['region'] ?? '')) ?: null,
            'municipality' => trim((string) ($payload['municipality'] ?? '')) ?: null,
        ]);
    }

    /** Смена пароля пользователя администратором. Возвращает текст ошибки или null при успехе. */
    public static function adminSetPassword(PDO $pdo, int $targetUserId, string $newPassword): ?string
    {
        if ($targetUserId <= 0) {
            return 'Некорректный пользователь.';
        }
        if (mb_strlen($newPassword) < 8) {
            return 'Пароль должен быть не короче 8 символов.';
        }
        $exists = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $exists->execute(['id' => $targetUserId]);
        if (!$exists->fetch()) {
            return 'Пользователь не найден.';
        }
        $upd = $pdo->prepare(
            'UPDATE users SET password_hash = :h, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $upd->execute([
            'h' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $targetUserId,
        ]);
        self::refreshOwnSessionFingerprint($targetUserId);

        return null;
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['iro_auth_fp']);
        session_regenerate_id(true);
    }
}
