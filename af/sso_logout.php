<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/sso.php';

header('Cache-Control: no-store');

$return = trim((string) ($_GET['return'] ?? ''));
$accept = (string) (audit_fund_sso_cfg()['sso_logout_accept_return'] ?? '');
$ticket = (string) ($_GET['ticket'] ?? '');
$returnOk = ecosystem_sso_logout_return_allowed($return, $accept);
$payload = $returnOk
    ? ecosystem_sso_token_verify($ticket, audit_fund_sso_secret(), 'af', 'vector', 'logout')
    : null;
if (!$returnOk) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Некорректный запрос выхода';
    exit;
}

if ($payload !== null) {
    audit_fund_session_start();
    $userId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : 0;
    if ($userId > 0) {
        audit_fund_invalidate_remember_for_user_id($userId);
    }
    audit_fund_clear_remember_cookie();
    $_SESSION = [];
    audit_fund_sso_expire_session_cookie();
    session_destroy();
}

$target = ecosystem_sso_with_skip($accept);
header('Location: ' . $target, true, 302);
exit;
