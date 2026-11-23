<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/schedule_data.php';
require_once __DIR__ . '/includes/audit_log.php';

audit_fund_require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: schedule.php');
    exit;
}
if (!audit_fund_verify_csrf(audit_fund_post_string('_csrf') ?: null)) {
    audit_fund_redirect_with_flash('schedule.php', audit_fund_schedule_flash_merge_week(['sched_move_err' => 'csrf']));
}

$entryId = audit_fund_post_int('entry_id');
$requestId = audit_fund_post_int('request_id');
$newDate = audit_fund_post_string('new_date');
$newRoomId = audit_fund_post_int('new_room_id');
$relocateTimeStart = audit_fund_post_string_key('relocate_time_start');
$relocateTimeEnd = audit_fund_post_string_key('relocate_time_end');

if ($requestId > 0) {
    $err = audit_fund_approved_request_booking_relocate($requestId, $newDate, $newRoomId, $relocateTimeStart, $relocateTimeEnd);
    if ($err === null) {
        audit_fund_log_action('move', 'request', (string) $requestId, [
            'new_date' => $newDate,
            'new_room_id' => $newRoomId,
            'relocate_time_start' => $relocateTimeStart,
            'relocate_time_end' => $relocateTimeEnd,
            'context' => 'schedule_calendar',
        ]);
        audit_fund_redirect_with_flash('schedule.php', audit_fund_schedule_flash_merge_week(['sched_moved' => 1]));
    }
} elseif ($entryId > 0) {
    $err = audit_fund_schedule_entry_relocate($entryId, $newDate, $newRoomId, $relocateTimeStart, $relocateTimeEnd);
    if ($err === null) {
        audit_fund_log_action('move', 'schedule_entry', (string) $entryId, [
            'new_date' => $newDate,
            'new_room_id' => $newRoomId,
            'relocate_time_start' => $relocateTimeStart,
            'relocate_time_end' => $relocateTimeEnd,
        ]);
        audit_fund_redirect_with_flash('schedule.php', audit_fund_schedule_flash_merge_week(['sched_moved' => 1]));
    }
} else {
    $err = 'invalid';
}

audit_fund_redirect_with_flash('schedule.php', audit_fund_schedule_flash_merge_week(['sched_move_err' => $err]));
