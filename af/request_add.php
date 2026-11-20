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

$returnTo = trim(audit_fund_post_string('return_to'));
if ($returnTo !== 'schedule') {
    $returnTo = '';
}

if (!audit_fund_verify_csrf(audit_fund_post_string('_csrf') ?: null)) {
    if ($returnTo === 'schedule') {
        $flash = audit_fund_schedule_flash_merge_week(['request_err' => 'csrf']);
        audit_fund_redirect_with_flash('schedule.php', $flash);
    }
    audit_fund_redirect_with_flash('requests.php', ['request_err' => 'csrf']);
}

$roomId = audit_fund_post_int('room_id');
$bookingDate = audit_fund_post_string('booking_date');
$timeStart = audit_fund_post_string('time_start');
$timeEnd = audit_fund_post_string('time_end');
$applicantDisplay = audit_fund_post_string('applicant_display');
$comment = audit_fund_post_string('comment');
$userId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : null;

$err = audit_fund_request_create($roomId, $bookingDate, $timeStart, $timeEnd, $applicantDisplay, $comment, $userId);
if ($err === null) {
    audit_fund_log_action('create', 'request', null, [
        'room_id' => $roomId,
        'booking_date' => $bookingDate,
        'time_start' => $timeStart,
        'time_end' => $timeEnd,
    ]);
    if ($returnTo === 'schedule') {
        $flash = audit_fund_schedule_flash_merge_week(['request_saved' => 1]);
        audit_fund_redirect_with_flash('schedule.php', $flash);
    }
    audit_fund_redirect_with_flash('requests.php', ['request_saved' => 1]);
}

$flashErr = ['request_err' => $err];
if ($err === 'busy' || $err === 'maint') {
    $hints = audit_fund_request_create_busy_hints($roomId, $bookingDate, $timeStart, $timeEnd);
    if ($hints !== '') {
        $flashErr['request_err_hints'] = $hints;
    }
}

if ($returnTo === 'schedule' && $err !== null) {
    $flashErr['request_modal_open'] = 1;
    $flashErr['request_modal_room_id'] = (string) max(0, $roomId);
    $flashErr['request_modal_booking_date'] = $bookingDate;
    $flashErr['request_modal_time_start'] = $timeStart;
    $flashErr['request_modal_time_end'] = $timeEnd;
    $applicantFlash = $applicantDisplay;
    if (function_exists('mb_substr')) {
        $applicantFlash = mb_substr($applicantFlash, 0, 255, 'UTF-8');
    } elseif (strlen($applicantFlash) > 255) {
        $applicantFlash = substr($applicantFlash, 0, 255);
    }
    $commentFlash = $comment;
    if (function_exists('mb_substr')) {
        $commentFlash = mb_substr($commentFlash, 0, 1000, 'UTF-8');
    } elseif (strlen($commentFlash) > 1000) {
        $commentFlash = substr($commentFlash, 0, 1000);
    }
    $flashErr['request_modal_applicant'] = $applicantFlash;
    $flashErr['request_modal_comment'] = $commentFlash;
}

if ($returnTo === 'schedule') {
    $flash = audit_fund_schedule_flash_merge_week($flashErr);
    audit_fund_redirect_with_flash('schedule.php', $flash);
}
audit_fund_redirect_with_flash('requests.php', $flashErr);
