<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/sso.php';

$wasLoggedIn = !empty($_SESSION['user_id']);
$peerLogout = planner_sso_https_url((string) (planner_sso_cfg()['peer_logout_url'] ?? ''));
$returnTo = planner_sso_https_url((string) (planner_sso_cfg()['logout_return_url'] ?? ''));
$logoutToken = ($wasLoggedIn && planner_sso_ready() && $peerLogout !== '' && $returnTo !== '')
    ? planner_sso_issue_logout_token()
    : '';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
if ($logoutToken !== '') {
    header('Location: ' . $peerLogout . '?' . http_build_query([
        'ticket' => $logoutToken,
        'return' => $returnTo,
    ], '', '&', PHP_QUERY_RFC3986), true, 302);
    exit;
}
header('Location: ' . planner_url('/login.php'), true, 302);
