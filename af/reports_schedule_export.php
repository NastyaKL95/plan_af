<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/reports_data.php';
require_once __DIR__ . '/includes/requests_data.php';
require_once __DIR__ . '/includes/xlsx_export.php';

audit_fund_require_staff();

$detailed = audit_fund_reports_schedule_detailed();
if ($detailed['error'] !== null) {
    header('Location: reports.php');
    exit;
}

$summary = audit_fund_reports_schedule_summary();
if ($summary['error'] !== null) {
    header('Location: reports.php');
    exit;
}

$summaryRows = [];
$sumEntries = 0;
$sumHours = 0.0;
foreach ($summary['rows'] as $row) {
    $entries = (int) $row['entries_count'];
    $hours = (float) $row['hours_total'];
    $sumEntries += $entries;
    $sumHours += $hours;
    $summaryRows[] = [
        (string) $row['building_name'],
        (string) $row['room_number'],
        (string) $entries,
        number_format($hours, 1, '.', ''),
    ];
}
$summaryRows[] = [
    '',
    'Итого',
    (string) $sumEntries,
    number_format($sumHours, 1, '.', ''),
];

$detailRows = [];
foreach ($detailed['rows'] as $row) {
    $day = date('d.m.Y', strtotime((string) $row['entry_date']));
    $time = substr((string) $row['time_start'], 0, 5) . '–' . audit_fund_request_time_end_display_hhmm((string) $row['time_end']);
    $detailRows[] = [
        $day,
        $time,
        (string) $row['room_number'] . ' — ' . (string) $row['building_name'],
        (string) ($row['comment'] ?? ''),
    ];
}

audit_fund_xlsx_output_workbook(
    'schedule-report-' . date('Y-m-d-H-i') . '.xlsx',
    [
        [
            'name' => 'Сводка',
            'headers' => ['Здание', 'Аудитория', 'Слотов', 'Часов'],
            'rows' => $summaryRows,
            'autofilter' => true,
        ],
        [
            'name' => 'Расписание',
            'headers' => ['День', 'Время', 'Аудитория', 'Комментарий'],
            'rows' => $detailRows,
            'autofilter' => true,
        ],
    ]
);
