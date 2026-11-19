<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/sso.php';

header('Cache-Control: no-store');

$return = trim((string) ($_GET['return'] ?? ''));
$state = (string) ($_GET['state'] ?? '');
$expectedReturn = trim((string) (planner_sso_cfg()['peer_consume_url'] ?? ''));
$stateOk = preg_match('/^[a-f0-9]{32,64}$/', $state) === 1;
if (!planner_sso_ready() || $expectedReturn === '' || !hash_equals($expectedReturn, $return) || !$stateOk) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Некорректный запрос общего входа';
    exit;
}

$identity = planner_sso_current_identity();
$status = (string) ($identity['status'] ?? 'none');
if ($status !== 'ok') {
    header('Location: ' . $return . '?' . http_build_query([
        'sso' => $status === 'denied' ? 'denied' : 'none',
        'state' => $state,
    ], '', '&', PHP_QUERY_RFC3986), true, 302);
    exit;
}

$token = planner_sso_issue_login_token($identity, $state);
header('Location: ' . $return . '?' . http_build_query([
    'ticket' => $token,
    'state' => $state,
], '', '&', PHP_QUERY_RFC3986), true, 302);
exit;
