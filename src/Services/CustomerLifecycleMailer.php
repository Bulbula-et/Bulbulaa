<?php
declare(strict_types=1);
namespace Bulbula\Services;
use Bulbula\Core\Mailer;
final class CustomerLifecycleMailer
{
 public function deletionScheduled(string $email,string $effectiveAt): void
 {
  $date=date('F j, Y',strtotime($effectiveAt)?:time());$subject='Your Bulbula.et account deletion is scheduled';
  $text="Your Bulbula.et account is scheduled for permanent deletion on {$date}. Sign in and verify your identity before that date to restore it. If you did not request this, restore your account immediately.";
  $html=$this->wrap('Account deletion scheduled','Your account is scheduled for permanent deletion on <strong>'.htmlspecialchars($date,ENT_QUOTES).'</strong>. Sign in and verify your identity before that date to restore it.<br><br>If you did not request this, restore your account immediately.');
  try{Mailer::send($email,$subject,$html,$text);}catch(\Throwable){}
 }
 public function restored(string $email): void { $this->send($email,'Your Bulbula.et account was restored','Account restored','Your account deletion was cancelled and your access has been restored. If you did not do this, change your password and review active sessions immediately.'); }
 public function deleted(string $email): void { $this->send($email,'Your Bulbula.et account was permanently deleted','Account permanently deleted','Your Bulbula.et sign-in identity and personal account information have been permanently erased. This action cannot be undone.'); }
 private function send(string $email,string $subject,string $heading,string $message): void {try{Mailer::send($email,$subject,$this->wrap($heading,htmlspecialchars($message,ENT_QUOTES)),$message);}catch(\Throwable){}}
 private function wrap(string $heading,string $body): string{return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:28px;color:#1f2937"><div style="color:#EA6A15;font-weight:800;font-size:20px">Bulbula.et</div><h1 style="font-size:24px;margin:20px 0 8px">'.htmlspecialchars($heading,ENT_QUOTES).'</h1><p style="line-height:1.6">'.$body.'</p><p style="font-size:13px;color:#6b7280">This is an account security notification.</p></div>';}
}
