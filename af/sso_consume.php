<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/sso.php';

header('Cache-Control: no-store');

if (audit_fund_is_logged_in()) {
    header('Location: schedule.php');
    exit;
}

$givenState = (string) ($_GET['state'] ?? '');
$expectedState = audit_fund_sso_expected_state();
$stateOk = $expectedState !== null && hash_equals($expectedState, $givenState);
$flag = (string) ($_GET['sso'] ?? '');

if ($flag === 'none' || $flag === 'denied' || $flag === 'error') {
    $query = 'error=sso_bad';
    if ($stateOk && $flag === 'none') {
        $query = 'sso=none';
    } elseif ($stateOk && $flag === 'denied') {
        $query = 'error=sso_denied';
    } elseif ($stateOk && $flag === 'error') {
        $code = (string) ($_GET['code'] ?? '');
        $query = preg_match('/^[a-z0-9_]{1,64}$/', $code) === 1 ? 'error=' . rawurlencode($code) : 'error=sso_bad';
    }
    header('Location: index.php?' . $query, true, 302);
    exit;
}

$ticket = (string) ($_GET['ticket'] ?? '');
if (!$stateOk || $ticket === '') {
    header('Location: index.php?error=sso_bad', true, 302);
    exit;
}

$payload = ecosystem_sso_token_verify($ticket, audit_fund_sso_secret(), 'af', 'vector', 'login');
if ($payload === null || !hash_equals($givenState, (string) ($payload['state'] ?? ''))) {
    header('Location: index.php?error=sso_bad', true, 302);
    exit;
}

$result = audit_fund_external_login(
    (string) $payload['email'],
    (string) ($payload['name'] ?? ''),
    (string) ($payload['provider'] ?? ''),
    (string) ($payload['subject'] ?? ''),
    true
);
if (empty($result['ok'])) {
    $error = (string) ($result['error'] ?? 'sso_bad');
    header('Location: index.php?error=' . rawurlencode($error), true, 302);
    exit;
}

header('Location: schedule.php');
exit;
