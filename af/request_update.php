<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/requests_data.php';
require_once __DIR__ . '/includes/audit_log.php';

audit_fund_require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: requests.php');
    exit;
}
if (!audit_fund_verify_csrf(audit_fund_post_string('_csrf') ?: null)) {
    audit_fund_redirect_with_flash('requests.php', ['request_update_err' => 'csrf']);
}

$userId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : 0;
$requestId = audit_fund_post_int('request_id');
$roomId = audit_fund_post_int('room_id');
$bookingDate = audit_fund_post_string('booking_date');
$timeStart = audit_fund_post_string('time_start');
$timeEnd = audit_fund_post_string('time_end');
$applicantDisplay = audit_fund_post_string('applicant_display');
$comment = audit_fund_post_string('comment');

$err = audit_fund_request_update_own_pending(
    $requestId,
    $userId,
    $roomId,
    $bookingDate,
    $timeStart,
    $timeEnd,
    $applicantDisplay,
    $comment
);
if ($err === null) {
    audit_fund_log_action('update', 'request', (string) $requestId, [
        'room_id' => $roomId,
        'booking_date' => $bookingDate,
        'time_start' => $timeStart,
        'time_end' => $timeEnd,
    ]);
    audit_fund_redirect_with_flash('requests.php', ['request_updated' => 1]);
}

audit_fund_redirect_with_flash('requests.php', ['request_update_err' => $err, 'request_upd_id' => $requestId]);
