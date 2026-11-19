<?php
declare(strict_types=1);

/**
 * Подписанный билет общего входа «Вектор» ↔ «Аудиторный фонд».
 * Формат: base64url(json).base64url(hmac-sha256). Секрет на обоих сайтах один и тот же.
 * Файл не обращается к БД и сессии. Копия в аудиторном фонде должна совпадать побайтно по правилам проверки.
 */

function ecosystem_sso_b64url_encode(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function ecosystem_sso_b64url_decode(string $encoded): ?string
{
    $encoded = trim($encoded);
    if ($encoded === '' || preg_match('/[^A-Za-z0-9_-]/', $encoded) === 1) {
        return null;
    }
    $pad = strlen($encoded) % 4;
    if ($pad > 0) {
        $encoded .= str_repeat('=', 4 - $pad);
    }
    $raw = base64_decode(strtr($encoded, '-_', '+/'), true);

    return $raw === false ? null : $raw;
}

/**
 * @param array<string, scalar|null> $payload
 */
function ecosystem_sso_token_sign(array $payload, string $secret): string
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $body = ecosystem_sso_b64url_encode($json);
    $sig = ecosystem_sso_b64url_encode(hash_hmac('sha256', $body, $secret, true));

    return $body . '.' . $sig;
}

/**
 * @return array<string, mixed>|null
 */
function ecosystem_sso_token_verify(
    string $token,
    string $secret,
    string $expectedAud,
    string $expectedIss,
    string $expectedPurpose
): ?array {
    $secret = trim($secret);
    if ($secret === '' || strlen($secret) < 32) {
        return null;
    }
    $parts = explode('.', trim($token), 3);
    if (count($parts) !== 2) {
        return null;
    }
    [$body, $sig] = $parts;
    $sigRaw = ecosystem_sso_b64url_decode($sig);
    if ($sigRaw === null) {
        return null;
    }
    $expectedSig = hash_hmac('sha256', $body, $secret, true);
    if (!hash_equals($expectedSig, $sigRaw)) {
        return null;
    }
    $json = ecosystem_sso_b64url_decode($body);
    if ($json === null) {
        return null;
    }
    try {
        $payload = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    if (!is_array($payload)) {
        return null;
    }
    if ((int) ($payload['v'] ?? 0) !== 1) {
        return null;
    }
    if ((string) ($payload['aud'] ?? '') !== $expectedAud || (string) ($payload['iss'] ?? '') !== $expectedIss) {
        return null;
    }
    if ((string) ($payload['purpose'] ?? '') !== $expectedPurpose) {
        return null;
    }
    $exp = (int) ($payload['exp'] ?? 0);
    $iat = (int) ($payload['iat'] ?? 0);
    $now = time();
    if ($exp < $now || $iat > $now + 30 || $exp <= $iat || ($exp - $iat) > 180) {
        return null;
    }
    if ($expectedPurpose === 'login') {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        $payload['email'] = $email;
        $payload['name'] = trim((string) ($payload['name'] ?? ''));
        $provider = trim((string) ($payload['provider'] ?? ''));
        $payload['provider'] = $provider === 'yandex' ? 'yandex' : '';
        $payload['subject'] = $payload['provider'] === 'yandex' ? trim((string) ($payload['subject'] ?? '')) : '';
        $state = (string) ($payload['state'] ?? '');
        if ($state !== '' && preg_match('/^[a-f0-9]{32,64}$/', $state) !== 1) {
            return null;
        }
        $payload['state'] = $state;
    }

    return $payload;
}

function ecosystem_sso_random_state(): string
{
    return bin2hex(random_bytes(16));
}
