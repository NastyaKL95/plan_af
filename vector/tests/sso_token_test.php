<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/sso_token.php';

$secret = 'af5f7b16f09a100d8e3588020c3f3ed198a559afffb92c2b7a740df8fb5feea6';
$now = time();
$payload = [
    'v' => 1,
    'purpose' => 'login',
    'iss' => 'vector',
    'aud' => 'af',
    'iat' => $now,
    'exp' => $now + 120,
    'jti' => 'abc',
    'state' => str_repeat('ab', 16),
    'email' => 'User@IRO75.ru',
    'name' => 'Иван',
    'provider' => 'yandex',
    'subject' => '12345',
];
$token = ecosystem_sso_token_sign($payload, $secret);
$got = ecosystem_sso_token_verify($token, $secret, 'af', 'vector', 'login');
if ($got === null || $got['email'] !== 'user@iro75.ru' || $got['name'] !== 'Иван' || $got['subject'] !== '12345') {
    fwrite(STDERR, "valid token was rejected\n");
    exit(1);
}
if (ecosystem_sso_token_verify($token, $secret, 'vector', 'af', 'login') !== null) {
    fwrite(STDERR, "audience swap was accepted\n");
    exit(1);
}
if (ecosystem_sso_token_verify($token . 'x', $secret, 'af', 'vector', 'login') !== null) {
    fwrite(STDERR, "tampered token was accepted\n");
    exit(1);
}
$payload['exp'] = $now - 5;
$expired = ecosystem_sso_token_sign($payload, $secret);
if (ecosystem_sso_token_verify($expired, $secret, 'af', 'vector', 'login') !== null) {
    fwrite(STDERR, "expired token was accepted\n");
    exit(1);
}
$payload['exp'] = $now + 120;
$payload['purpose'] = 'logout';
unset($payload['email'], $payload['name'], $payload['provider'], $payload['subject'], $payload['state']);
$logout = ecosystem_sso_token_sign($payload, $secret);
if (ecosystem_sso_token_verify($logout, $secret, 'af', 'vector', 'logout') === null) {
    fwrite(STDERR, "logout token was rejected\n");
    exit(1);
}
if (ecosystem_sso_token_verify($logout, $secret, 'af', 'vector', 'login') !== null) {
    fwrite(STDERR, "logout token was accepted as login\n");
    exit(1);
}
$other = dirname(__DIR__, 2) . '/af/includes/sso_token.php';
if (is_file($other) && md5_file($other) !== md5_file(__DIR__ . '/../includes/sso_token.php')) {
    fwrite(STDERR, "sso_token.php copies differ\n");
    exit(1);
}

echo "sso token ok\n";
