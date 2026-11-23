<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/xlsx_export.php';

audit_fund_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: audit_log.php', true, 302);
    exit;
}
if (!audit_fund_verify_csrf(audit_fund_post_string('_csrf') ?: null)) {
    header('Location: audit_log.php', true, 302);
    exit;
}

$dateFrom = trim(audit_fund_post_string('date_from'));
$dateTo = trim(audit_fund_post_string('date_to'));
$userQuery = trim(audit_fund_post_string('user'));

$logData = audit_fund_log_list(
    $dateFrom !== '' ? $dateFrom : null,
    $dateTo !== '' ? $dateTo : null,
    $userQuery !== '' ? $userQuery : null,
    null
);

if ($logData['error'] !== null) {
    header('Location: audit_log.php');
    exit;
}

$rows = [];
foreach ($logData['rows'] as $row) {
    $created = isset($row['created_at']) ? strtotime((string) $row['created_at']) : false;
    $when = $created !== false ? date('d.m.Y H:i:s', $created) : (string) ($row['created_at'] ?? '');
    $rows[] = [
        $when,
        (string) ($row['user_display'] ?? 'Система'),
        audit_fund_audit_action_label((string) $row['action']),
        audit_fund_audit_entity_label((string) $row['entity_type']),
        (string) ($row['entity_id'] ?? ''),
        audit_fund_audit_details_excel_cell(isset($row['details_json']) ? (string) $row['details_json'] : null),
    ];
}

audit_fund_xlsx_output(
    'audit-log-' . date('Y-m-d-H-i') . '.xlsx',
    'Аудит-лог',
    ['Дата и время', 'Имя в системе', 'Действие', 'Сущность', 'ID сущности', 'Детали'],
    $rows
);
