<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/redirect_flash.php';
require_once __DIR__ . '/includes/sso.php';

audit_fund_session_start();

if (!audit_fund_is_logged_in()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    audit_fund_redirect_with_flash('schedule.php', ['logout_err' => 'get']);
}

if (!audit_fund_verify_csrf(audit_fund_post_string('_csrf') ?: null)) {
    audit_fund_redirect_with_flash('schedule.php', ['logout_err' => 'csrf']);
}

$userId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : 0;
if ($userId > 0) {
    audit_fund_invalidate_remember_for_user_id($userId);
}

audit_fund_clear_remember_cookie();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

$peerLogout = audit_fund_sso_https_url((string) (audit_fund_db_config()['sso_peer_logout_url'] ?? ''));
$returnTo = audit_fund_sso_https_url((string) (audit_fund_db_config()['sso_logout_return_url'] ?? ''));
$logoutToken = (audit_fund_sso_ready() && $peerLogout !== '' && $returnTo !== '')
    ? audit_fund_sso_issue_logout_token()
    : '';

session_destroy();
if ($logoutToken !== '') {
    header('Location: ' . $peerLogout . '?' . http_build_query([
        'ticket' => $logoutToken,
        'return' => $returnTo,
    ], '', '&', PHP_QUERY_RFC3986));
    exit;
}
header('Location: index.php');
exit;
