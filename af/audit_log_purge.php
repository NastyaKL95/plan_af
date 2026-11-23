<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/redirect_flash.php';

audit_fund_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: audit_log.php');
    exit;
}

if (!audit_fund_verify_csrf(audit_fund_post_string('_csrf') ?: null)) {
    audit_fund_redirect_with_flash('audit_log.php', ['audit_purge_err' => 'csrf']);
}

$mode = audit_fund_post_string('purge_mode');

if ($mode === 'all') {
    if (audit_fund_post_string('confirm_all') !== '1') {
        audit_fund_redirect_with_flash('audit_log.php', ['audit_purge_err' => 'confirm']);
    }
    $res = audit_fund_log_purge('all');
} elseif ($mode === 'before') {
    $before = audit_fund_post_string('before_date');
    $res = audit_fund_log_purge('before', $before);
} elseif ($mode === 'range') {
    $df = audit_fund_post_string('purge_date_from');
    $dt = audit_fund_post_string('purge_date_to');
    $res = audit_fund_log_purge('range', null, $df, $dt);
} else {
    audit_fund_redirect_with_flash('audit_log.php', ['audit_purge_err' => 'invalid']);
}

if ($res['error'] !== null) {
    audit_fund_redirect_with_flash('audit_log.php', ['audit_purge_err' => $res['error']]);
}

audit_fund_log_action('delete', 'audit_log', null, [
    'purge_mode' => $mode,
    'deleted_rows' => $res['deleted'],
]);

audit_fund_redirect_with_flash('audit_log.php', [
    'audit_purge_ok' => 1,
    'audit_purged_count' => $res['deleted'],
]);
