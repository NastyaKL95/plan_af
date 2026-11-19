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
    audit_fund_redirect_with_flash('schedule.php', audit_fund_schedule_flash_merge_week(['maint_del_err' => 'csrf']));
}

$windowId = audit_fund_post_int('window_id');
$err = audit_fund_maintenance_window_delete($windowId);
if ($err === null) {
    audit_fund_log_action('delete', 'maintenance_window', (string) $windowId);
    audit_fund_redirect_with_flash('schedule.php', audit_fund_schedule_flash_merge_week(['maint_deleted' => 1]));
}

audit_fund_redirect_with_flash('schedule.php', audit_fund_schedule_flash_merge_week(['maint_del_err' => $err]));
