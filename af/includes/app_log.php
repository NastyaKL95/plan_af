<?php
declare(strict_types=1);

/**
 * Запись исключений в журнал PHP (error_log) для разбора на сервере без показа деталей пользователю.
 */
function audit_fund_log_exception(Throwable $e, string $context = ''): void
{
    $prefix = $context !== '' ? "[audit-fund:{$context}] " : '[audit-fund] ';
    $line = $prefix . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    error_log($line . "\n" . $e->getTraceAsString());
}
