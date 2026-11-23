<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * @return array{rows:list<array<string,mixed>>,error:string|null}
 */
function audit_fund_reports_requests_summary(): array
{
    $out = ['rows' => [], 'error' => null];

    try {
        $pdo = audit_fund_pdo();
        $stmt = $pdo->query(
            'SELECT status, COUNT(*) AS requests_count
             FROM booking_requests
             GROUP BY status
             ORDER BY FIELD(status, "pending", "approved", "rejected")'
        );
        $out['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'reports_data');
        $out['error'] = 'Не удалось сформировать отчёт по заявкам.';
    }

    return $out;
}

/**
 * @return array{rows:list<array<string,mixed>>,error:string|null}
 */
function audit_fund_reports_requests_detailed(): array
{
    $out = ['rows' => [], 'error' => null];

    try {
        $pdo = audit_fund_pdo();
        $stmt = $pdo->query(
            'SELECT br.id, br.request_number, br.applicant_display, br.booking_date, br.time_start, br.time_end,
                    br.status, br.submitted_at, r.room_number, b.name AS building_name,
                    COALESCE(NULLIF(TRIM(u.display_name), \'\'), u.username) AS requested_by_username
             FROM booking_requests br
             INNER JOIN rooms r ON r.id = br.room_id
             INNER JOIN buildings b ON b.id = r.building_id
             LEFT JOIN users u ON u.id = br.requested_by_user_id
             ORDER BY br.submitted_at DESC, br.id DESC'
        );
        $out['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'reports_data');
        $out['error'] = 'Не удалось сформировать детальный отчёт по заявкам.';
    }

    return $out;
}

/**
 * @return array{rows:list<array<string,mixed>>,error:string|null}
 */
function audit_fund_reports_schedule_summary(): array
{
    $out = ['rows' => [], 'error' => null];

    try {
        $pdo = audit_fund_pdo();
        $stmt = $pdo->query(
            'SELECT b.name AS building_name, r.room_number, COUNT(se.id) AS entries_count,
                    ROUND(SUM(TIMESTAMPDIFF(MINUTE, se.time_start, se.time_end)) / 60, 1) AS hours_total
             FROM schedule_entries se
             INNER JOIN rooms r ON r.id = se.room_id
             INNER JOIN buildings b ON b.id = r.building_id
             GROUP BY b.id, b.name, r.id, r.room_number
             ORDER BY b.sort_order, b.name, r.room_number'
        );
        $out['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'reports_data');
        $out['error'] = 'Не удалось сформировать отчёт по расписанию.';
    }

    return $out;
}

/**
 * @return array{rows:list<array<string,mixed>>,error:string|null}
 */
function audit_fund_reports_schedule_detailed(): array
{
    $out = ['rows' => [], 'error' => null];

    try {
        $pdo = audit_fund_pdo();
        $stmt = $pdo->query(
            'SELECT se.entry_date, se.time_start, se.time_end, se.comment,
                    r.room_number, b.name AS building_name
             FROM schedule_entries se
             INNER JOIN rooms r ON r.id = se.room_id
             INNER JOIN buildings b ON b.id = r.building_id
             ORDER BY se.entry_date, se.time_start, b.sort_order, b.name, r.room_number'
        );
        $out['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'reports_data');
        $out['error'] = 'Не удалось сформировать детальный отчёт по расписанию.';
    }

    return $out;
}

/**
 * @return array{rows:list<array<string,mixed>>,total:int,error:string|null}
 */
function audit_fund_reports_requests_detailed_paged(int $page = 1, int $perPage = 20): array
{
    $out = ['rows' => [], 'total' => 0, 'error' => null];
    $perPage = max(5, min(100, $perPage));
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;

    try {
        $pdo = audit_fund_pdo();
        $out['total'] = (int) $pdo->query('SELECT COUNT(*) FROM booking_requests')->fetchColumn();
        $stmt = $pdo->prepare(
            'SELECT br.id, br.request_number, br.applicant_display, br.booking_date, br.time_start, br.time_end,
                    br.status, br.submitted_at, r.room_number, b.name AS building_name,
                    COALESCE(NULLIF(TRIM(u.display_name), \'\'), u.username) AS requested_by_username
             FROM booking_requests br
             INNER JOIN rooms r ON r.id = br.room_id
             INNER JOIN buildings b ON b.id = r.building_id
             LEFT JOIN users u ON u.id = br.requested_by_user_id
             ORDER BY br.submitted_at DESC, br.id DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute();
        $out['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'reports_data');
        $out['error'] = 'Не удалось сформировать детальный отчёт по заявкам.';
    }

    return $out;
}

/**
 * @return array{rows:list<array<string,mixed>>,total:int,error:string|null}
 */
function audit_fund_reports_schedule_detailed_paged(int $page = 1, int $perPage = 20): array
{
    $out = ['rows' => [], 'total' => 0, 'error' => null];
    $perPage = max(5, min(100, $perPage));
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;

    try {
        $pdo = audit_fund_pdo();
        $out['total'] = (int) $pdo->query('SELECT COUNT(*) FROM schedule_entries')->fetchColumn();
        $stmt = $pdo->prepare(
            'SELECT se.entry_date, se.time_start, se.time_end, se.comment,
                    r.room_number, b.name AS building_name
             FROM schedule_entries se
             INNER JOIN rooms r ON r.id = se.room_id
             INNER JOIN buildings b ON b.id = r.building_id
             ORDER BY se.entry_date, se.time_start, b.sort_order, b.name, r.room_number
             LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute();
        $out['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        audit_fund_log_exception($e, 'reports_data');
        $out['error'] = 'Не удалось сформировать детальный отчёт по расписанию.';
    }

    return $out;
}

function audit_fund_report_request_status_label(string $status): string
{
    switch ($status) {
        case 'approved':
            return 'Одобрено';
        case 'rejected':
            return 'Отклонено';
        default:
            return 'На рассмотрении';
    }
}
