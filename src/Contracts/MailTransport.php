<?php
declare(strict_types=1);

namespace Bulbula\Contracts;

interface MailTransport
{
    /** @return array{ok:bool,error?:string,transport?:string} */
    public function send(string $to, string $subject, string $html, string $text = ''): array;
}
