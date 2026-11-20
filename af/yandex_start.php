<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/sso.php';
require_once __DIR__ . '/includes/yandex_oauth.php';

if (audit_fund_is_logged_in()) {
    header('Location: schedule.php');
    exit;
}

audit_fund_session_start();

if (audit_fund_yandex_direct_ready()) {
    $cfg = audit_fund_yandex_config();
    try {
        $state = bin2hex(random_bytes(16));
    } catch (Throwable) {
        $state = sha1(uniqid('yx_', true));
    }
    $_SESSION['audit_fund_yandex_state'] = $state;
    $_SESSION['audit_fund_yandex_state_ts'] = time();
    $query = http_build_query([
        'response_type' => 'code',
        'client_id' => $cfg['client_id'],
        'redirect_uri' => $cfg['redirect_uri'],
        'scope' => $cfg['scope'],
        'state' => $state,
    ], '', '&', PHP_QUERY_RFC3986);
    header('Location: https://oauth.yandex.ru/authorize?' . $query, true, 302);
    exit;
}

if (audit_fund_yandex_relay_ready()) {
    $state = ecosystem_sso_random_state();
    $_SESSION['audit_fund_sso_state'] = $state;
    $_SESSION['audit_fund_sso_state_ts'] = time();
    $start = audit_fund_sso_https_url((string) (audit_fund_sso_cfg()['sso_peer_yandex_start_url'] ?? ''));
    $sep = str_contains($start, '?') ? '&' : '?';
    header('Location: ' . $start . $sep . http_build_query([
        'relay' => 'af',
        'sso_state' => $state,
    ], '', '&', PHP_QUERY_RFC3986), true, 302);
    exit;
}

header('Location: index.php?error=yandex_not_configured');
exit;
