<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/directories_data.php';
require_once __DIR__ . '/includes/audit_log.php';

audit_fund_require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: directories.php');
    exit;
}
if (!audit_fund_verify_csrf(audit_fund_post_string('_csrf') ?: null)) {
    audit_fund_redirect_with_flash('directories.php', ['room_type_upd_err' => 'csrf']);
}

$roomTypeId = audit_fund_post_int('room_type_id');
$name = audit_fund_post_string('name');
$description = audit_fund_post_string('description');

$err = audit_fund_room_type_update($roomTypeId, $name, $description);
if ($err === null) {
    audit_fund_log_action('update', 'room_type', (string) $roomTypeId, ['name' => $name]);
    audit_fund_redirect_with_flash('directories.php', ['room_type_updated' => 1]);
}

audit_fund_redirect_with_flash('directories.php', ['room_type_upd_err' => $err, 'room_type_upd_id' => $roomTypeId]);
