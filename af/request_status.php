<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/requests_data.php';
require_once __DIR__ . '/includes/audit_log.php';

audit_fund_require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: requests.php');
    exit;
}
if (!audit_fund_verify_csrf(audit_fund_post_string('_csrf') ?: null)) {
    audit_fund_redirect_with_flash('requests.php', ['request_status_err' => 'csrf']);
}

$requestId = audit_fund_post_int('request_id');
$status = audit_fund_post_string('status');
$rejectionReason = audit_fund_post_string('rejection_reason');

$err = audit_fund_request_set_status_with_reason($requestId, $status, $rejectionReason);
if ($err === null) {
    audit_fund_log_action($status === 'approved' ? 'approve' : 'reject', 'request', (string) $requestId, [
        'status' => $status,
        'rejection_reason' => $rejectionReason,
    ]);
    $okCode = $status === 'approved' ? 'approved' : 'rejected';
    audit_fund_redirect_with_flash('requests.php', ['request_status_ok' => $okCode]);
}

audit_fund_redirect_with_flash('requests.php', ['request_status_err' => $err, 'request_status_id' => $requestId]);
