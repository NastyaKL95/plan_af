<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/sso.php';

header('Cache-Control: no-store');

$return = trim((string) ($_GET['return'] ?? ''));
$accept = trim((string) (planner_sso_cfg()['logout_accept_return'] ?? ''));
$ticket = (string) ($_GET['ticket'] ?? '');
$ok = $accept !== ''
    && hash_equals($accept, $return)
    && ecosystem_sso_token_verify($ticket, planner_sso_secret(), 'vector', 'af', 'logout') !== null;

if (!$ok) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Некорректный запрос выхода';
    exit;
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: ' . $return, true, 302);
exit;
