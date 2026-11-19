<?php
/**
 * Обновление аудитории (форма из management.php).
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
    audit_fund_redirect_with_flash('management.php', ['room_upd_err' => 'csrf']);
}

$roomId = audit_fund_post_int('room_id');
$roomNumber = audit_fund_post_string('room_number');
$buildingId = audit_fund_post_int('building_id');
$roomTypeId = audit_fund_post_int('room_type_id');
$colorHex = audit_fund_post_string('color_hex');
$floorRaw = audit_fund_post_string_key('floor');
$floor = ($floorRaw !== null && $floorRaw !== '') ? (int) $floorRaw : -1;
$capRaw = audit_fund_post_string_key('capacity');
$capacity = ($capRaw !== null && $capRaw !== '') ? (int) $capRaw : -1;
$status = audit_fund_post_string('operational_status');

$err = audit_fund_room_update($roomId, $roomNumber, $buildingId, $roomTypeId, $colorHex, $floor, $capacity, $status);

if ($err === null) {
    audit_fund_log_action('update', 'room', (string) $roomId, [
        'room_number' => $roomNumber,
        'building_id' => $buildingId,
        'room_type_id' => $roomTypeId,
    ]);
    audit_fund_redirect_with_flash('management.php', ['room_updated' => 1]);
}

audit_fund_redirect_with_flash('management.php', ['room_upd_err' => $err, 'room_upd_id' => $roomId]);
