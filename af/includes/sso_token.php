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

/** Почта принадлежит одному из доменов. Сравнение домена без учёта регистра. */
function ecosystem_email_domain_allowed(string $email, array $domains): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }
    $at = strrchr($email, '@');
    $domain = $at === false ? '' : strtolower(substr($at, 1));
    if ($domain === '') {
        return false;
    }
    foreach ($domains as $item) {
        if (strtolower(trim((string) $item)) === $domain) {
            return true;
        }
    }

    return false;
}

/**
 * Слова ФИО длиннее двух букв: инициалы вроде «Д.С.» не считаются.
 *
 * @return list<string>
 */
function ecosystem_person_name_tokens(string $name): array
{
    $name = mb_strtolower(trim($name), 'UTF-8');
    $name = str_replace(['ё', 'Ё'], ['е', 'е'], $name);
    $name = preg_replace('/[^\p{L}]+/u', ' ', $name) ?? '';
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        return [];
    }
    $tokens = [];
    foreach ($parts as $part) {
        if (mb_strlen($part, 'UTF-8') >= 3) {
            $tokens[$part] = true;
        }
    }
    $list = array_keys($tokens);
    sort($list);

    return $list;
}

/**
 * Одно и то же ФИО, если набор слов совпал или отличается только отчеством.
 * Одной фамилии недостаточно.
 */
function ecosystem_person_names_match(string $left, string $right): bool
{
    $a = ecosystem_person_name_tokens($left);
    $b = ecosystem_person_name_tokens($right);
    if ($a === [] || $b === []) {
        return false;
    }
    if ($a === $b) {
        return true;
    }
    $small = count($a) <= count($b) ? $a : $b;
    $large = count($a) <= count($b) ? $b : $a;
    if (count($small) < 2 || count($large) - count($small) > 1) {
        return false;
    }
    $have = array_flip($large);
    foreach ($small as $token) {
        if (!isset($have[$token])) {
            return false;
        }
    }

    return true;
}

/** Адрес общего входа: только https, без хвоста «/», хост в нижнем регистре. */
function ecosystem_sso_normalize_https_url(string $value): string
{
    $value = trim($value);
    if (preg_match('#^https://#i', $value) !== 1) {
        return '';
    }
    $parts = parse_url($value);
    if (!is_array($parts) || empty($parts['host'])) {
        return '';
    }
    $host = strtolower((string) $parts['host']);
    $port = isset($parts['port']) ? (int) $parts['port'] : 0;
    $path = (string) ($parts['path'] ?? '/');
    if ($path === '') {
        $path = '/';
    }
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
    }
    $query = [];
    if (isset($parts['query']) && $parts['query'] !== '') {
        parse_str((string) $parts['query'], $query);
    }
    ksort($query);
    $built = 'https://' . $host;
    if ($port > 0 && $port !== 443) {
        $built .= ':' . $port;
    }
    $built .= $path;
    if ($query !== []) {
        $built .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    return $built;
}

function ecosystem_sso_urls_match(string $left, string $right): bool
{
    $a = ecosystem_sso_normalize_https_url($left);
    $b = ecosystem_sso_normalize_https_url($right);
    if ($a === '' || $b === '') {
        return false;
    }

    return strlen($a) === strlen($b) && hash_equals($b, $a);
}

/** Совпадение адреса возврата после выхода. Параметр sso=skip не мешает сравнению. */
function ecosystem_sso_logout_return_allowed(string $return, string $expected): bool
{
    if (ecosystem_sso_urls_match($return, $expected)) {
        return true;
    }
    $a = ecosystem_sso_without_skip($return);
    $b = ecosystem_sso_without_skip($expected);

    return $a !== '' && $b !== '' && strlen($a) === strlen($b) && hash_equals($b, $a);
}

function ecosystem_sso_without_skip(string $url): string
{
    $url = ecosystem_sso_normalize_https_url($url);
    if ($url === '') {
        return '';
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return '';
    }
    $query = [];
    if (isset($parts['query']) && $parts['query'] !== '') {
        parse_str((string) $parts['query'], $query);
    }
    if (($query['sso'] ?? null) === 'skip') {
        unset($query['sso']);
    }
    ksort($query);
    $built = 'https://' . strtolower((string) $parts['host']);
    if (isset($parts['port'])) {
        $built .= ':' . (int) $parts['port'];
    }
    $built .= (string) ($parts['path'] ?? '/');
    if ($query !== []) {
        $built .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    return $built;
}

/** После выхода не запускать общий вход сразу снова. */
function ecosystem_sso_with_skip(string $url): string
{
    $url = ecosystem_sso_normalize_https_url($url);
    if ($url === '') {
        return '';
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return '';
    }
    $query = [];
    if (isset($parts['query']) && $parts['query'] !== '') {
        parse_str((string) $parts['query'], $query);
    }
    $query['sso'] = 'skip';
    ksort($query);
    $built = 'https://' . strtolower((string) $parts['host']);
    if (isset($parts['port'])) {
        $built .= ':' . (int) $parts['port'];
    }
    $built .= (string) ($parts['path'] ?? '/');
    $built .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

    return $built;
}
