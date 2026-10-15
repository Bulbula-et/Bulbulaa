<?php
declare(strict_types=1);
namespace Bulbula\Services;
use Bulbula\Contracts\MailTransport;use Bulbula\Infrastructure\PhpMailerTransport;
final class StaffCampaignMailer
{
 public function __construct(private readonly MailTransport $transport=new PhpMailerTransport()){}
 public function terminated(array $campaign):array
 {
  $code=(string)$campaign['campaign_code'];$branch=(string)$campaign['branch_name_en'];$reason=(string)$campaign['termination_reason'];
  $subject='Campaign '.$code.' was terminated early';$text="Campaign {$code} for {$branch} was terminated early and removed from delivery. Reason: {$reason}. Historical delivery metrics remain available in the Staff Console.";
  $html='<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:28px;color:#1f2937"><div style="color:#EA6A15;font-weight:800;font-size:20px">Bulbula.et</div><h1 style="font-size:24px">Campaign terminated early</h1><p>'.htmlspecialchars($text,ENT_QUOTES).'</p><p style="font-size:13px;color:#6b7280">This operational notice does not determine billing, refund, tax, or contractual obligations.</p></div>';
  return $this->transport->send((string)$campaign['creator_email'],$subject.' — Bulbula.et',$html,$text);
 }
}
