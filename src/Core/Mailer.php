<?php
declare(strict_types=1);

namespace Bulbula\Core;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Transactional mail adapter backed by the mature PHPMailer open-source project.
 * Keeps vendor behavior behind a small Bulbula.et API for maintainability.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $html, string $text = ''): array
    {
        if (!class_exists(PHPMailer::class)) {
            return ['ok' => false, 'error' => 'PHPMailer is not installed. Run composer install.'];
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid recipient email address.'];
        }

        $mail = new PHPMailer(true);
        try {
            $driver = (string) Config::get('mail.driver', 'smtp');
            if ($driver === 'mail') {
                $mail->isMail();
            } else {
                $host       = trim((string) Config::get('mail.host', ''));
                $username   = trim((string) Config::get('mail.username', ''));
                $password   = (string) Config::get('mail.password', '');
                $encryption = (string) Config::get('mail.encryption', 'tls');
                if ($host === '' || $username === '' || $password === '') {
                    return ['ok' => false, 'error' => 'SMTP host, username, or password is not configured.'];
                }

                $mail->isSMTP();
                $mail->Host       = $host;
                $mail->Port       = (int) Config::get('mail.port', 587);
                $mail->SMTPAuth   = true;
                $mail->Username   = $username;
                $mail->Password   = $password;
                $mail->Timeout    = 12;
                $mail->SMTPKeepAlive = false;

                if ($encryption === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($encryption === 'tls') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $mail->SMTPSecure = '';
                    $mail->SMTPAutoTLS = false;
                }
            }

            $fromEmail = (string) Config::get('mail.from_email', 'no-reply@bulbula.et');
            $fromName  = (string) Config::get('mail.from_name', 'Bulbula.et Verification');
            if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => 'Configured sender email is invalid.'];
            }

            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = $text !== '' ? $text : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));
            $mail->send();

            return ['ok' => true, 'transport' => $driver === 'mail' ? 'mail' : 'smtp'];
        } catch (PHPMailerException $e) {
            return ['ok' => false, 'error' => 'Mail transport failed: ' . $mail->ErrorInfo];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Mail transport failed safely.'];
        }
    }
}
