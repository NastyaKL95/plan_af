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

$pairs = [
    ['Клопова Анастасия Владимировна', 'Клопова Анастасия Владимировна', true],
    ['Людмила Портнова', 'Портнова Людмила Константиновна', true],
    ['Татьяна Рыбак', 'Рыбак Татьяна Георгиевна', true],
    ['Светлана Кузьмина', 'Светлана Владимировна Кузьмина', true],
    ['Светлана Дабаевна', 'Цыдыпова Светлана Дабаевна', true],
    ['Кузьмин Д.С.', 'Кузьмин Денис Сергеевич', false],
    ['Иван', 'Иван Иванов', false],
    ['', 'Клопова Анастасия', false],
];
foreach ($pairs as [$left, $right, $expect]) {
    $got = ecosystem_person_names_match($left, $right);
    if ($got !== $expect) {
        fwrite(STDERR, "name match failed: {$left} / {$right}\n");
        exit(1);
    }
}

$consume = 'https://af.zabedu.ru/sso_consume.php';
if (!ecosystem_sso_urls_match($consume, 'HTTPS://AF.ZABEDU.RU/sso_consume.php/')) {
    fwrite(STDERR, "consume url was not normalized\n");
    exit(1);
}
if (ecosystem_sso_urls_match($consume, 'https://evil.example/sso_consume.php')) {
    fwrite(STDERR, "foreign consume url was accepted\n");
    exit(1);
}
if (ecosystem_sso_normalize_https_url('http://af.zabedu.ru/sso_consume.php') !== '') {
    fwrite(STDERR, "http return was accepted\n");
    exit(1);
}
$home = 'https://af.zabedu.ru/index.php';
$homeSkip = 'https://af.zabedu.ru/index.php?sso=skip';
if (!ecosystem_sso_logout_return_allowed($home, $homeSkip) || !ecosystem_sso_logout_return_allowed($homeSkip, $home)) {
    fwrite(STDERR, "logout return with sso=skip was rejected\n");
    exit(1);
}
if (ecosystem_sso_logout_return_allowed('https://af.zabedu.ru/schedule.php', $home)) {
    fwrite(STDERR, "logout return to another page was accepted\n");
    exit(1);
}
if (ecosystem_sso_with_skip($home) !== $homeSkip || ecosystem_sso_with_skip($homeSkip) !== $homeSkip) {
    fwrite(STDERR, "sso=skip was not applied\n");
    exit(1);
}

echo "sso token ok\n";
