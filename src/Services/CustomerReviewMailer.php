<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Contracts\MailTransport;
use Bulbula\Core\Config;
use Bulbula\Infrastructure\PhpMailerTransport;

final class CustomerReviewMailer
{
    public function __construct(private readonly MailTransport $transport = new PhpMailerTransport()) {}

    /** @return array{ok:bool,event:string,error?:string,transport?:string} */
    public function send(array $context, string $event): array
    {
        $message = $this->message($context, $event);
        if ($message === null) return ['ok'=>false,'event'=>$event,'error'=>'Unsupported review notification event.'];
        $result = $this->transport->send((string)($context['customer_email']??''), $message['subject'], $message['html'], $message['text']);
        return ['event'=>$event] + $result;
    }

    /** @return array{subject:string,html:string,text:string}|null */
    public function message(array $context, string $event): ?array
    {
        $branch = trim((string)($context['branch_name_en']??'this Branch')) ?: 'this Branch';
        $business = trim((string)($context['business_name_en']??''));
        $place = $business !== '' && $business !== $branch ? $business . ' — ' . $branch : $branch;
        $reason = trim((string)($context['moderation_reason_code']??''));
        $templates = [
            'SUBMITTED'=>['Review received','We received your review of '.$place.'. It is pending moderation and is not public yet.'],
            'EDITED'=>['Updated review received','We received your updated review of '.$place.'. It returned to moderation and is not public while it is reviewed.'],
            'PUBLISHED'=>['Your review is now published','Your review of '.$place.' passed moderation and is now public.'],
            'REJECTED'=>['Your review was not published','Your review of '.$place.' did not pass moderation'.($reason!==''?' (reason: '.$this->reasonLabel($reason).')':'').'. You can review its status from your account.'],
            'REMOVED_BY_STAFF'=>['Your review was removed','Your previously published review of '.$place.' was removed after moderation'.($reason!==''?' (reason: '.$this->reasonLabel($reason).')':'').'. You can review its status from your account.'],
        ];
        if (!isset($templates[$event])) return null;
        [$subject,$body]=$templates[$event];
        $url=rtrim((string)Config::get('app.url','https://bulbula.et'),'/').'/account/reviews';
        $text=$body."\n\nReview your activity: ".$url."\n\nThis is a transactional update about your Bulbula.et review.";
        $html='<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:28px;color:#1f2937"><div style="color:#EA6A15;font-weight:800;font-size:20px">Bulbula.et</div><h1 style="font-size:24px;margin:20px 0 8px">'.htmlspecialchars($subject,ENT_QUOTES).'</h1><p style="line-height:1.6">'.htmlspecialchars($body,ENT_QUOTES).'</p><p><a href="'.htmlspecialchars($url,ENT_QUOTES).'" style="color:#C9550D;font-weight:700">Review your activity</a></p><p style="font-size:13px;color:#6b7280">This is a transactional update about your Bulbula.et review. It does not include your review text or private moderation notes.</p></div>';
        return ['subject'=>$subject.' — Bulbula.et','html'=>$html,'text'=>$text];
    }

    private function reasonLabel(string $code): string
    {
        return strtolower(str_replace('_',' ',preg_replace('/[^A-Z0-9_]/','',strtoupper($code))??''));
    }
}
