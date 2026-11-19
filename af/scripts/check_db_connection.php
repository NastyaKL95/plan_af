<?php
declare(strict_types=1);

$ports = [3308, 3306, 3307];
foreach ($ports as $port) {
    try {
        $dsn = sprintf('mysql:host=127.0.0.1;port=%d;dbname=audit_fund;charset=utf8mb4', $port);
        $pdo = new PDO($dsn, 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->query('SELECT 1');
        echo "OK: порт {$port}, БД audit_fund доступна.\n";
        exit(0);
    } catch (Throwable $e) {
        echo "FAIL {$port}: " . $e->getMessage() . "\n";
    }
}
exit(1);
