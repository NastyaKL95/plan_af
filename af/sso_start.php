<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/sso.php';

if (audit_fund_is_logged_in()) {
    header('Location: schedule.php');
    exit;
}
if (!audit_fund_sso_ready()) {
    header('Location: index.php?error=sso_not_configured');
    exit;
}
audit_fund_sso_begin_peer_check();
header('Location: index.php?error=sso_not_configured');
exit;
