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
    audit_fund_redirect_with_flash('schedule.php', audit_fund_schedule_flash_merge_week(['maint_upd_err' => 'csrf']));
}

$windowId = audit_fund_post_int('window_id');
$roomId = audit_fund_post_int('room_id');
$maintDate = audit_fund_post_string('maint_date');
$timeStart = audit_fund_post_string('time_start');
$timeEnd = audit_fund_post_string('time_end');
$reason = audit_fund_post_string('reason');

$err = audit_fund_maintenance_window_update($windowId, $roomId, $maintDate, $timeStart, $timeEnd, $reason);
if ($err === null) {
    audit_fund_log_action('update', 'maintenance_window', (string) $windowId, [
        'room_id' => $roomId,
        'maint_date' => $maintDate,
        'time_start' => $timeStart,
        'time_end' => $timeEnd,
        'reason' => $reason,
    ]);
    audit_fund_redirect_with_flash('schedule.php', audit_fund_schedule_flash_merge_week(['maint_updated' => 1]));
}

audit_fund_redirect_with_flash('schedule.php', audit_fund_schedule_flash_merge_week(['maint_upd_err' => $err, 'maint_upd_id' => $windowId]));
