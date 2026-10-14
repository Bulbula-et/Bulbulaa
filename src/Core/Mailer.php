<?php
declare(strict_types=1);

namespace Bulbula\Core;

/**
 * Dependency-free transactional mailer for shared hosting.
 * Supports local PHP mail() and authenticated SMTP with STARTTLS/implicit TLS.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $html, string $text = ''): array
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid recipient email address.'];
        }

        $driver = (string) Config::get('mail.driver', 'smtp');
        $from   = (string) Config::get('mail.from_email', 'no-reply@bulbula.et');
        $name   = (string) Config::get('mail.from_name', 'Bulbula.et');
        $text   = $text !== '' ? $text : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));

        if ($driver === 'mail') {
            $boundary = 'bulbula_' . bin2hex(random_bytes(8));
            $headers  = "From: " . self::encodeHeader($name) . " <{$from}>\r\n";
            $headers .= "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $body  = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$text}\r\n";
            $body .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n--{$boundary}--\r\n";
            $ok = @mail($to, self::encodeHeader($subject), $body, $headers);
            return $ok ? ['ok' => true, 'transport' => 'mail'] : ['ok' => false, 'error' => 'PHP mail() rejected the message.'];
        }

        return self::sendSmtp($to, $subject, $html, $text, $from, $name);
    }

    private static function sendSmtp(string $to, string $subject, string $html, string $text, string $from, string $fromName): array
    {
        $host       = (string) Config::get('mail.host', '');
        $port       = (int) Config::get('mail.port', 587);
        $encryption = (string) Config::get('mail.encryption', 'tls');
        $username   = (string) Config::get('mail.username', '');
        $password   = (string) Config::get('mail.password', '');

        if ($host === '' || $username === '' || $password === '') {
            return ['ok' => false, 'error' => 'SMTP host, username, or password is not configured.'];
        }

        $target = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $errno = 0; $errstr = '';
        $socket = @stream_socket_client($target, $errno, $errstr, 12, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            return ['ok' => false, 'error' => "SMTP connection failed ({$errno}): {$errstr}"];
        }
        stream_set_timeout($socket, 12);

        try {
            self::expect($socket, [220]);
            $hostname = parse_url((string) Config::get('app.url', 'https://bulbula.et'), PHP_URL_HOST) ?: 'bulbula.et';
            self::command($socket, "EHLO {$hostname}", [250]);

            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('SMTP STARTTLS negotiation failed.');
                }
                self::command($socket, "EHLO {$hostname}", [250]);
            }

            self::command($socket, 'AUTH LOGIN', [334]);
            self::command($socket, base64_encode($username), [334]);
            self::command($socket, base64_encode($password), [235]);
            self::command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);

            $boundary = 'bulbula_' . bin2hex(random_bytes(10));
            $message  = 'Date: ' . date(DATE_RFC2822) . "\r\n";
            $message .= 'From: ' . self::encodeHeader($fromName) . " <{$from}>\r\n";
            $message .= "To: <{$to}>\r\nSubject: " . self::encodeHeader($subject) . "\r\n";
            $message .= "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
            $message .= "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$text}\r\n";
            $message .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$html}\r\n";
            $message .= "--{$boundary}--\r\n";
            $message = preg_replace('/(?m)^\./', '..', $message) ?? $message;
            fwrite($socket, $message . "\r\n.\r\n");
            self::expect($socket, [250]);
            self::command($socket, 'QUIT', [221]);
            fclose($socket);
            return ['ok' => true, 'transport' => 'smtp'];
        } catch (\Throwable $e) {
            fclose($socket);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private static function command($socket, string $command, array $codes): string
    {
        fwrite($socket, $command . "\r\n");
        return self::expect($socket, $codes);
    }

    private static function expect($socket, array $codes): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException('SMTP error ' . $code . ': ' . trim($response));
        }
        return $response;
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], '', $value)) . '?=';
    }
}
