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
    audit_fund_redirect_with_flash('schedule.php', audit_fund_schedule_flash_merge_week(['sched_del_err' => 'csrf']));
}

$entryId = audit_fund_post_int('entry_id');
$requestId = audit_fund_post_int('request_id');

if (($entryId > 0 && $requestId > 0) || ($entryId <= 0 && $requestId <= 0)) {
    audit_fund_redirect_with_flash('schedule.php', audit_fund_schedule_flash_merge_week(['sched_del_err' => 'invalid']));
}

if ($requestId > 0) {
    $err = audit_fund_approved_request_slot_remove($requestId);
    if ($err === null) {
        audit_fund_log_action('reject', 'request', (string) $requestId, ['reason' => audit_fund_schedule_slot_removal_reason()]);
        audit_fund_redirect_with_flash('schedule.php', audit_fund_schedule_flash_merge_week(['sched_deleted' => 1]));
    }
} else {
    $err = audit_fund_schedule_entry_delete($entryId);
    if ($err === null) {
        audit_fund_log_action('delete', 'schedule_entry', (string) $entryId);
        audit_fund_redirect_with_flash('schedule.php', audit_fund_schedule_flash_merge_week(['sched_deleted' => 1]));
    }
}

audit_fund_redirect_with_flash('schedule.php', audit_fund_schedule_flash_merge_week(['sched_del_err' => $err]));
