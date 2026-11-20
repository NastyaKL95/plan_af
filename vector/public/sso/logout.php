<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/sso.php';

header('Cache-Control: no-store');

$return = trim((string) ($_GET['return'] ?? ''));
$accept = (string) (planner_sso_cfg()['logout_accept_return'] ?? '');
$ticket = (string) ($_GET['ticket'] ?? '');
$returnOk = ecosystem_sso_logout_return_allowed($return, $accept);
$payload = $returnOk
    ? ecosystem_sso_token_verify($ticket, planner_sso_secret(), 'vector', 'af', 'logout')
    : null;
if (!$returnOk) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Некорректный запрос выхода';
    exit;
}

if ($payload !== null) {
    $_SESSION = [];
    planner_sso_expire_session_cookie();
    session_destroy();
}

$target = ecosystem_sso_with_skip($accept);
header('Location: ' . $target, true, 302);
exit;
