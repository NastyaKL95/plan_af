<?php

declare(strict_types=1);

/**
 * Простая SMTP-отправка для восстановления пароля.
 * Включается блоком mail в config (enabled + smtp_* + from_email).
 */

function iro_mail_config(?array $config = null): array
{
    $config ??= ($GLOBALS['config'] ?? []);
    $m = $config['mail'] ?? null;

    return is_array($m) ? $m : [];
}

function iro_mail_enabled(?array $config = null): bool
{
    $m = iro_mail_config($config);

    return !empty($m['enabled'])
        && trim((string) ($m['from_email'] ?? '')) !== ''
        && trim((string) ($m['smtp_host'] ?? '')) !== ''
        && trim((string) ($m['smtp_user'] ?? '')) !== ''
        && (string) ($m['smtp_pass'] ?? '') !== '';
}

function iro_mail_address_ok(string $email): bool
{
    $email = trim($email);

    return $email !== ''
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        && !preg_match('/[\s<>,;]/u', $email);
}

function iro_mail_header_encode(string $text): string
{
    if ($text === '') {
        return '';
    }

    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function iro_mail_log(string $msg): void
{
    $dir = env_path('storage/logs');
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents(
        $dir . DIRECTORY_SEPARATOR . 'mail.log',
        '[' . date('c') . '] ' . $msg . PHP_EOL,
        FILE_APPEND
    );
}

function iro_mail_read_response($fp): string
{
    $resp = '';
    while (!feof($fp)) {
        $line = (string) fgets($fp, 1024);
        if ($line === '') {
            break;
        }
        $resp .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    return $resp;
}

/**
 * @param resource $fp
 * @param list<int> $okCodes
 */
function iro_mail_expect($fp, array $okCodes): string
{
    $resp = iro_mail_read_response($fp);
    $code = (int) substr($resp, 0, 3);
    if (!in_array($code, $okCodes, true)) {
        throw new RuntimeException('smtp_bad_response:' . trim($resp));
    }

    return $resp;
}

/**
 * @param resource $fp
 * @param list<int> $okCodes
 */
function iro_mail_cmd($fp, string $cmd, array $okCodes): string
{
    fwrite($fp, $cmd . "\r\n");

    return iro_mail_expect($fp, $okCodes);
}

function iro_send_mail(string $toEmail, string $subject, string $plainBody, ?array $config = null): bool
{
    $config ??= ($GLOBALS['config'] ?? []);
    $toEmail = trim($toEmail);
    if (!iro_mail_address_ok($toEmail) || !iro_mail_enabled($config)) {
        return false;
    }

    $m = iro_mail_config($config);
    $host = trim((string) ($m['smtp_host'] ?? ''));
    $port = (int) ($m['smtp_port'] ?? 465);
    $enc = strtolower(trim((string) ($m['smtp_encryption'] ?? 'ssl')));
    $user = trim((string) ($m['smtp_user'] ?? ''));
    $pass = (string) ($m['smtp_pass'] ?? '');
    $fromEmail = trim((string) ($m['from_email'] ?? ''));
    $fromName = trim((string) ($m['from_name'] ?? 'Конкурсы'));
    $timeout = max(3, (int) ($m['smtp_timeout'] ?? 12));

    $transportHost = $enc === 'ssl' ? 'ssl://' . $host : $host;
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client($transportHost . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        iro_mail_log('connect_error ' . $errno . ' ' . $errstr);

        return false;
    }
    stream_set_timeout($fp, $timeout);

    try {
        iro_mail_expect($fp, [220]);
        iro_mail_cmd($fp, 'EHLO konkurs.local', [250]);

        if ($enc === 'tls') {
            iro_mail_cmd($fp, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('smtp_tls_failed');
            }
            iro_mail_cmd($fp, 'EHLO konkurs.local', [250]);
        }

        iro_mail_cmd($fp, 'AUTH LOGIN', [334]);
        iro_mail_cmd($fp, base64_encode($user), [334]);
        iro_mail_cmd($fp, base64_encode($pass), [235]);

        iro_mail_cmd($fp, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        iro_mail_cmd($fp, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
        iro_mail_cmd($fp, 'DATA', [354]);

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . iro_mail_header_encode($fromName) . ' <' . $fromEmail . '>',
            'To: <' . $toEmail . '>',
            'Subject: ' . iro_mail_header_encode($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: IroKonkursSMTP',
        ];

        $body = str_replace(["\r\n", "\r"], "\n", $plainBody);
        $body = str_replace("\n.", "\n..", $body);
        $raw = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $body) . "\r\n.";
        fwrite($fp, $raw . "\r\n");
        iro_mail_expect($fp, [250]);
        iro_mail_cmd($fp, 'QUIT', [221]);
        fclose($fp);

        return true;
    } catch (Throwable $e) {
        iro_mail_log('send_error ' . $e->getMessage());
        @fclose($fp);

        return false;
    }
}
