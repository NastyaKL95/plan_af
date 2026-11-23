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
    audit_fund_redirect_with_flash('directories.php', ['building_upd_err' => 'csrf']);
}

$buildingId = audit_fund_post_int('building_id');
$name = audit_fund_post_string('name');
$address = audit_fund_post_string('address');

$err = audit_fund_building_update($buildingId, $name, $address);
if ($err === null) {
    audit_fund_log_action('update', 'building', (string) $buildingId, ['name' => $name]);
    audit_fund_redirect_with_flash('directories.php', ['building_updated' => 1]);
}

audit_fund_redirect_with_flash('directories.php', ['building_upd_err' => $err, 'building_upd_id' => $buildingId]);
