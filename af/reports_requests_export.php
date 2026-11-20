<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/reports_data.php';
require_once __DIR__ . '/includes/requests_data.php';
require_once __DIR__ . '/includes/xlsx_export.php';

audit_fund_require_staff();

$detailed = audit_fund_reports_requests_detailed();
if ($detailed['error'] !== null) {
    header('Location: reports.php');
    exit;
}

$summary = audit_fund_reports_requests_summary();
if ($summary['error'] !== null) {
    header('Location: reports.php');
    exit;
}

$summaryRows = [];
$totalCount = 0;
foreach ($summary['rows'] as $row) {
    $cnt = (int) $row['requests_count'];
    $totalCount += $cnt;
    $summaryRows[] = [
        audit_fund_report_request_status_label((string) $row['status']),
        (string) $cnt,
    ];
}
$summaryRows[] = ['Итого', (string) $totalCount];

$detailRows = [];
foreach ($detailed['rows'] as $row) {
    $submitted = date('d.m.Y', strtotime((string) $row['submitted_at']));
    $room = (string) $row['room_number'] . ' — ' . (string) $row['building_name'];
    $period = date('d.m.Y', strtotime((string) $row['booking_date'])) . ', '
        . substr((string) $row['time_start'], 0, 5) . '–' . audit_fund_request_time_end_display_hhmm((string) $row['time_end']);
    $status = audit_fund_report_request_status_label((string) $row['status']);

    $byUser = isset($row['requested_by_username']) && trim((string) $row['requested_by_username']) !== ''
        ? (string) $row['requested_by_username']
        : '';

    $detailRows[] = [
        (string) $row['request_number'],
        $submitted,
        $room,
        $period,
        (string) $row['applicant_display'],
        $byUser,
        $status,
    ];
}

audit_fund_xlsx_output_workbook(
    'requests-report-' . date('Y-m-d-H-i') . '.xlsx',
    [
        [
            'name' => 'Сводка',
            'headers' => ['Статус', 'Количество заявок'],
            'rows' => $summaryRows,
            'autofilter' => true,
        ],
        [
            'name' => 'Заявки',
            'headers' => ['№', 'Подана', 'Аудитория', 'Период', 'Заявитель', 'Имя пользователя', 'Статус'],
            'rows' => $detailRows,
            'autofilter' => true,
        ],
    ]
);
