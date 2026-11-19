<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/sso.php';

header('Cache-Control: no-store');

$return = trim((string) ($_GET['return'] ?? ''));
$accept = trim((string) (audit_fund_db_config()['sso_logout_accept_return'] ?? ''));
$ticket = (string) ($_GET['ticket'] ?? '');
$ok = $accept !== ''
    && hash_equals($accept, $return)
    && ecosystem_sso_token_verify($ticket, audit_fund_sso_secret(), 'af', 'vector', 'logout') !== null;

if (!$ok) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Некорректный запрос выхода';
    exit;
}

audit_fund_session_start();
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
session_destroy();
header('Location: ' . $return, true, 302);
exit;
