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
    audit_fund_redirect_with_flash('directories.php', ['building_del_err' => 'csrf']);
}

$buildingId = audit_fund_post_int('building_id');
$err = audit_fund_building_delete($buildingId);

if ($err === null) {
    audit_fund_log_action('delete', 'building', (string) $buildingId);
    audit_fund_redirect_with_flash('directories.php', ['building_deleted' => 1]);
}

audit_fund_redirect_with_flash('directories.php', ['building_del_err' => $err]);
