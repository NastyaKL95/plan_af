<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/sso.php';

header('Cache-Control: no-store');

$return = trim((string) ($_GET['return'] ?? ''));
$state = strtolower(trim((string) ($_GET['state'] ?? '')));
$expectedReturn = (string) (audit_fund_sso_cfg()['sso_peer_consume_url'] ?? '');
$stateOk = preg_match('/^[a-f0-9]{32,64}$/', $state) === 1;
$returnOk = ecosystem_sso_urls_match($return, $expectedReturn);
if (!$returnOk || !$stateOk) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Некорректный запрос общего входа';
    exit;
}

$target = ecosystem_sso_normalize_https_url($expectedReturn);
if (!audit_fund_sso_ready()) {
    header('Location: ' . $target . '?' . http_build_query([
        'sso' => 'none',
        'state' => $state,
    ], '', '&', PHP_QUERY_RFC3986), true, 302);
    exit;
}

$identity = audit_fund_sso_current_identity();
$status = (string) ($identity['status'] ?? 'none');
if ($status !== 'ok') {
    header('Location: ' . $target . '?' . http_build_query([
        'sso' => $status === 'denied' ? 'denied' : 'none',
        'state' => $state,
    ], '', '&', PHP_QUERY_RFC3986), true, 302);
    exit;
}

$token = audit_fund_sso_issue_login_token($identity, $state);
header('Location: ' . $target . '?' . http_build_query([
    'ticket' => $token,
    'state' => $state,
], '', '&', PHP_QUERY_RFC3986), true, 302);
exit;
