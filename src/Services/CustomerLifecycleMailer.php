<?php
declare(strict_types=1);
namespace Bulbula\Services;
use Bulbula\Core\Mailer;
final class CustomerLifecycleMailer
{
    public function deletionScheduled(string $email, string $effectiveAt): void
    {
        $date = date('F j, Y', strtotime($effectiveAt) ?: time());
        $text = "Your Bulbula.et account is scheduled for permanent deletion on {$date}. Sign in and verify your identity before that date to restore it. If you did not request this, restore your account immediately.";
        $body = 'Your account is scheduled for permanent deletion on <strong>'.htmlspecialchars($date,ENT_QUOTES).'</strong>. Sign in and verify your identity before that date to restore it.<br><br>If you did not request this, restore your account immediately.';
        $this->send('deletion_scheduled', $email, 'Your Bulbula.et account deletion is scheduled', 'Account deletion scheduled', $text, $body);
    }
    public function restored(string $email): void { $this->send('account_restored',$email,'Your Bulbula.et account was restored','Account restored','Your account deletion was cancelled and your access has been restored. If you did not do this, change your password and review active sessions immediately.'); }
    public function deleted(string $email): void { $this->send('account_deleted',$email,'Your Bulbula.et account was permanently deleted','Account permanently deleted','Your Bulbula.et sign-in identity and personal account information have been permanently erased. This action cannot be undone.'); }
    private function send(string $key, string $email, string $subject, string $heading, string $text, ?string $body = null): void
    {
        $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:28px;color:#1f2937"><div style="color:#EA6A15;font-weight:800;font-size:20px">Bulbula.et</div><h1 style="font-size:24px;margin:20px 0 8px">'.htmlspecialchars($heading,ENT_QUOTES).'</h1><p style="line-height:1.6">'.($body ?? htmlspecialchars($text,ENT_QUOTES)).'</p><p style="font-size:13px;color:#6b7280">This is an account security notification.</p></div>';
        try {
            $message = (new EmailTemplateService())->render($key, ['message'=>$text], ['subject'=>$subject,'html'=>$html,'text'=>$text]);
            Mailer::send($email, $message['subject'], $message['html'], $message['text']);
        } catch (\Throwable) {}
    }
}
