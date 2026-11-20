<?php
declare(strict_types=1);

/**
 * JSON-проверка слота заявки до отправки формы (занятость, обслуживание, валидация даты/времени).
 */
require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/requests_data.php';

audit_fund_require_auth();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(
        ['ok' => false, 'code' => 'method', 'message' => 'Допустим только POST.', 'hints' => ''],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    exit;
}

if (!audit_fund_verify_csrf(audit_fund_post_string('_csrf') ?: null)) {
    http_response_code(403);
    echo json_encode(
        ['ok' => false, 'code' => 'csrf', 'message' => AUDIT_FUND_MSG_CSRF, 'hints' => ''],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    exit;
}

$roomId = audit_fund_post_int('room_id');
$bookingDate = audit_fund_post_string('booking_date');
$timeStart = audit_fund_post_string('time_start');
$timeEnd = audit_fund_post_string('time_end');

$out = audit_fund_request_precheck_slot($roomId, $bookingDate, $timeStart, $timeEnd);
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
