<?php
declare(strict_types=1);
namespace Bulbula\Services;
use Bulbula\Core\Mailer;
final class CustomerSecurityMailer
{
 public function passwordChanged(string $email,string $context='changed'): void { $this->send($email,'Your Bulbula.et password was '.$context,'Password '.$context,'Your account password was '.$context.'. All other signed-in devices were removed. If this was not you, use password recovery immediately.'); }
 public function emailChanged(string $oldEmail,string $newEmail): void
 {
  $this->send($oldEmail,'Your Bulbula.et sign-in email was changed','Sign-in email changed','Your Bulbula.et sign-in email was changed to '.$newEmail.'. If this was not you, contact support and secure your email account immediately.');
  $this->send($newEmail,'Your new Bulbula.et sign-in email is active','New sign-in email active','This email address is now the verified sign-in email for your Bulbula.et account. All other signed-in devices were removed.');
 }
 private function send(string $email,string $subject,string $heading,string $message): void
 {
  $safeHeading=htmlspecialchars($heading,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$safeMessage=htmlspecialchars($message,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  $html='<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:28px;color:#1f2937"><div style="color:#EA6A15;font-weight:800;font-size:20px">Bulbula.et</div><h1 style="font-size:24px;margin:20px 0 8px">'.$safeHeading.'</h1><p style="line-height:1.6">'.$safeMessage.'</p><p style="font-size:13px;color:#6b7280">This is an account security notification. Bulbula.et will never ask for your password or verification code.</p></div>';
  try{Mailer::send($email,$subject,$html,$message);}catch(\Throwable){}
 }
}
