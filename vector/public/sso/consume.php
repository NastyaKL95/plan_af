<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/sso.php';

header('Cache-Control: no-store');

$existing = current_user();
if ($existing && planner_login_block_message($existing) === null) {
    header('Location: ' . planner_public_site_url('/index.php'), true, 302);
    exit;
}

$givenState = (string) ($_GET['state'] ?? '');
$expectedState = planner_sso_expected_state();
$stateOk = $expectedState !== null && hash_equals($expectedState, $givenState);
$flag = (string) ($_GET['sso'] ?? '');

if ($flag === 'none' || $flag === 'denied') {
    $query = 'error=sso_bad';
    if ($stateOk && $flag === 'none') {
        $query = 'sso=none';
    } elseif ($stateOk && $flag === 'denied') {
        $query = 'error=sso_denied';
    }
    header('Location: ' . planner_public_site_url('/login.php?' . $query), true, 302);
    exit;
}

$ticket = (string) ($_GET['ticket'] ?? '');
if (!$stateOk || $ticket === '') {
    header('Location: ' . planner_public_site_url('/login.php?error=sso_bad'), true, 302);
    exit;
}

$payload = ecosystem_sso_token_verify($ticket, planner_sso_secret(), 'vector', 'af', 'login');
if ($payload === null || !hash_equals($givenState, (string) ($payload['state'] ?? ''))) {
    header('Location: ' . planner_public_site_url('/login.php?error=sso_bad'), true, 302);
    exit;
}

$result = planner_sso_accept_identity($payload);
if (empty($result['ok'])) {
    $error = (string) ($result['error'] ?? 'sso_bad');
    header('Location: ' . planner_public_site_url('/login.php?error=' . rawurlencode($error)), true, 302);
    exit;
}

header('Location: ' . planner_public_site_url('/index.php'), true, 302);
exit;
