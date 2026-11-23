<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    private static ?PDO $externalAuthConnection = null;

    public static function connection(array $config): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $db = $config['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['host'],
            $db['port'],
            $db['dbname']
        );

        self::$connection = new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connection;
    }

    /** PDO к внешней БД для проверки логина/пароля; null если отключено в конфиге. */
    public static function externalAuthConnection(array $config): ?PDO
    {
        $ea = $config['external_auth'] ?? null;
        if (!is_array($ea) || empty($ea['enabled'])) {
            return null;
        }

        if (self::$externalAuthConnection instanceof PDO) {
            return self::$externalAuthConnection;
        }

        $db = $ea;
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['host'],
            (int) ($db['port'] ?? 3306),
            $db['dbname']
        );

        $timeout = max(1, min(120, (int) ($ea['connect_timeout'] ?? 10)));
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
            $opts[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = $timeout;
        }

        self::$externalAuthConnection = new PDO($dsn, $db['user'], $db['password'], $opts);

        return self::$externalAuthConnection;
    }

    /**
     * Фрагмент для FROM: `table` или `database`.`table` (имена только [A-Za-z0-9_]).
     */
    public static function externalAuthTableReference(string $tableSpec): ?string
    {
        $tableSpec = trim($tableSpec);
        if ($tableSpec === '') {
            return null;
        }
        if (str_contains($tableSpec, '.')) {
            $parts = explode('.', $tableSpec, 2);
            $db = self::mysqlIdentifier(trim($parts[0]));
            $tbl = self::mysqlIdentifier(trim($parts[1] ?? ''));
            if ($db === null || $tbl === null) {
                return null;
            }

            return "`{$db}`.`{$tbl}`";
        }
        $tbl = self::mysqlIdentifier($tableSpec);

        return $tbl !== null ? "`{$tbl}`" : null;
    }

    private static function mysqlIdentifier(string $name): ?string
    {
        if ($name === '' || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            return null;
        }

        return $name;
    }
}
