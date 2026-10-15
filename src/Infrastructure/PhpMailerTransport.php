<?php
declare(strict_types=1);

namespace Bulbula\Infrastructure;

use Bulbula\Contracts\MailTransport;
use Bulbula\Core\Mailer;

final class PhpMailerTransport implements MailTransport
{
    public function send(string $to, string $subject, string $html, string $text = ''): array
    {
        return Mailer::send($to, $subject, $html, $text);
    }
}
