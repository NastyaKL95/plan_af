<?php
declare(strict_types=1);

require_once __DIR__ . '/sso.php';

/**
 * Вход через Яндекс ID.
 * direct — свой redirect URI на этом сайте.
 * relay — через уже зарегистрированный callback Вектора, затем общий билет обратно сюда.
 */

function audit_fund_yandex_config(): array
{
    $cfg = audit_fund_db_config();

    return [
        'mode' => (string) ($cfg['yandex_oauth_mode'] ?? 'relay'),
        'enabled' => !empty($cfg['yandex_oauth_enabled']),
        'client_id' => trim((string) ($cfg['yandex_client_id'] ?? '')),
        'client_secret' => trim((string) ($cfg['yandex_client_secret'] ?? '')),
        'redirect_uri' => trim((string) ($cfg['yandex_redirect_uri'] ?? '')),
        'scope' => trim((string) ($cfg['yandex_scope'] ?? 'login:email login:info')) ?: 'login:email login:info',
    ];
}

function audit_fund_yandex_direct_ready(): bool
{
    $cfg = audit_fund_yandex_config();

    return $cfg['enabled']
        && $cfg['mode'] === 'direct'
        && $cfg['client_id'] !== ''
        && $cfg['client_secret'] !== ''
        && preg_match('#^https://#', $cfg['redirect_uri']) === 1;
}

function audit_fund_yandex_relay_ready(): bool
{
    if (empty(audit_fund_yandex_config()['enabled']) || !audit_fund_sso_ready()) {
        return false;
    }
    $start = trim((string) (audit_fund_db_config()['sso_peer_yandex_start_url'] ?? ''));

    return preg_match('#^https://#', $start) === 1;
}

function audit_fund_yandex_button_enabled(): bool
{
    return audit_fund_yandex_direct_ready() || audit_fund_yandex_relay_ready();
}

/**
 * @return array{status:int,body:string}|null
 */
function audit_fund_yandex_http_post_form(string $url, array $fields): ?array
{
    $body = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        return ['status' => $code, 'body' => $raw];
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    return ['status' => 200, 'body' => $raw];
}

/**
 * @return array{status:int,body:string}|null
 */
function audit_fund_yandex_http_get_json(string $url, string $bearer): ?array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: OAuth ' . $bearer]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        return ['status' => $code, 'body' => $raw];
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: OAuth {$bearer}\r\n",
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    return ['status' => 200, 'body' => $raw];
}
