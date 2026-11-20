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
    audit_fund_redirect_with_flash('directories.php', ['building_err' => 'csrf']);
}

$name = audit_fund_post_string('name');
$address = audit_fund_post_string('address');

$res = audit_fund_building_insert($name, $address);
if ($res['error'] === null) {
    audit_fund_log_action('create', 'building', null, [
        'code' => (string) ($res['code'] ?? ''),
        'name' => $name,
    ]);
    audit_fund_redirect_with_flash('directories.php', ['building_saved' => 1]);
}

audit_fund_redirect_with_flash('directories.php', ['building_err' => $res['error']]);
