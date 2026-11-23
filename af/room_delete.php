<?php
/**
 * Удаление аудитории (действие из списка management.php).
 */
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/management_data.php';
require_once __DIR__ . '/includes/audit_log.php';

audit_fund_require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: management.php');
    exit;
}
if (!audit_fund_verify_csrf(audit_fund_post_string('_csrf') ?: null)) {
    audit_fund_redirect_with_flash('management.php', ['room_del_err' => 'csrf']);
}

$roomId = audit_fund_post_int('room_id');
$err = audit_fund_room_delete($roomId);

if ($err === null) {
    audit_fund_log_action('delete', 'room', (string) $roomId);
    audit_fund_redirect_with_flash('management.php', ['room_deleted' => 1]);
}

audit_fund_redirect_with_flash('management.php', ['room_del_err' => $err]);
