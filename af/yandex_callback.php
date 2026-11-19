<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/sso.php';
require_once __DIR__ . '/includes/yandex_oauth.php';

function audit_fund_yandex_fail(string $error): void
{
    header('Location: index.php?error=' . rawurlencode($error), true, 302);
    exit;
}

if (audit_fund_is_logged_in()) {
    header('Location: schedule.php');
    exit;
}

if (!audit_fund_yandex_direct_ready()) {
    audit_fund_yandex_fail('yandex_not_configured');
}

audit_fund_session_start();
$cfg = audit_fund_yandex_config();
$state = (string) ($_GET['state'] ?? '');
$expected = (string) ($_SESSION['audit_fund_yandex_state'] ?? '');
$stateTs = (int) ($_SESSION['audit_fund_yandex_state_ts'] ?? 0);
unset($_SESSION['audit_fund_yandex_state'], $_SESSION['audit_fund_yandex_state_ts']);
if ($state === '' || $expected === '' || !hash_equals($expected, $state) || (time() - $stateTs) > 600) {
    audit_fund_yandex_fail('yandex_bad_state');
}
if (!empty($_GET['error'])) {
    audit_fund_yandex_fail('yandex_access_denied');
}
$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '') {
    audit_fund_yandex_fail('yandex_no_code');
}

$tokenResp = audit_fund_yandex_http_post_form('https://oauth.yandex.ru/token', [
    'grant_type' => 'authorization_code',
    'code' => $code,
    'client_id' => $cfg['client_id'],
    'client_secret' => $cfg['client_secret'],
]);
if ($tokenResp === null) {
    audit_fund_yandex_fail('yandex_token_http');
}
try {
    $tokenData = json_decode((string) $tokenResp['body'], true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    audit_fund_yandex_fail('yandex_token_json');
}
$accessToken = trim((string) ($tokenData['access_token'] ?? ''));
if ($accessToken === '') {
    audit_fund_yandex_fail('yandex_token_missing');
}

$infoResp = audit_fund_yandex_http_get_json('https://login.yandex.ru/info?format=json', $accessToken);
if ($infoResp === null) {
    audit_fund_yandex_fail('yandex_profile_http');
}
try {
    $profile = json_decode((string) $infoResp['body'], true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    audit_fund_yandex_fail('yandex_profile_json');
}
if (!is_array($profile)) {
    audit_fund_yandex_fail('yandex_profile_json');
}

$subject = trim((string) ($profile['id'] ?? $profile['sub'] ?? ''));
$email = strtolower(trim((string) ($profile['default_email'] ?? '')));
$name = trim((string) ($profile['real_name'] ?? ''));
if ($name === '') {
    $name = trim((string) (($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')));
}
if ($subject === '') {
    audit_fund_yandex_fail('yandex_subject_missing');
}
if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    audit_fund_yandex_fail('yandex_email_missing');
}

$result = audit_fund_external_login($email, $name, 'yandex', $subject, audit_fund_registration_open());
if (empty($result['ok'])) {
    $error = (string) ($result['error'] ?? 'sso_bad');
    if ($error === 'sso_no_account') {
        $error = 'yandex_registration_closed';
    }
    audit_fund_yandex_fail($error);
}

header('Location: schedule.php');
exit;
