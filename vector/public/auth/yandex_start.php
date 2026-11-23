<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/sso.php';

$relay = (string) ($_GET['relay'] ?? '');
$ssoState = strtolower((string) ($_GET['sso_state'] ?? ''));
if ($relay === 'af' && preg_match('/^[a-f0-9]{32,64}$/', $ssoState) === 1 && planner_sso_ready()) {
    $_SESSION['oauth_yandex_relay'] = 'af';
    $_SESSION['oauth_yandex_sso_state'] = $ssoState;
} else {
    unset($_SESSION['oauth_yandex_relay'], $_SESSION['oauth_yandex_sso_state']);
}

function oauth_base_url(string $path): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/auth/yandex_start.php'));
    $base = rtrim(str_replace('\\', '/', dirname($scriptName, 2)), '/');
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
}

if (current_user()) {
    planner_sso_finish_redirect();
}

$oauthCfg = app_config()['oauth']['yandex'] ?? [];
$enabled = !empty($oauthCfg['enabled']);
$clientId = trim((string) ($oauthCfg['client_id'] ?? ''));
$redirectUri = trim((string) ($oauthCfg['redirect_uri'] ?? ''));
$scope = trim((string) ($oauthCfg['scope'] ?? 'login:email login:info'));

if (!$enabled || $clientId === '' || $redirectUri === '') {
    planner_sso_oauth_fail('yandex_not_configured');
}

try {
    $state = bin2hex(random_bytes(16));
} catch (Throwable) {
    $state = sha1(uniqid('yx_', true));
}
$_SESSION['oauth_yandex_state'] = $state;
$_SESSION['oauth_yandex_state_ts'] = time();

$query = http_build_query([
    'response_type' => 'code',
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'scope' => $scope,
    'state' => $state,
], '', '&', PHP_QUERY_RFC3986);

header('Location: https://oauth.yandex.ru/authorize?' . $query, true, 302);
exit;
