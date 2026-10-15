<?php
declare(strict_types=1);
use Bulbula\Contracts\MailTransport;use Bulbula\Services\StaffCampaignMailer;use PHPUnit\Framework\TestCase;
final class StaffCampaignMailerTest extends TestCase
{
 public function testTerminationNoticePreservesOperationalBoundary():void
 {
  $transport=new class implements MailTransport{public array $message=[];public function send(string $to,string $subject,string $html,string $text=''):array{$this->message=compact('to','subject','html','text');return ['ok'=>true,'transport'=>'test'];}};
  $result=(new StaffCampaignMailer($transport))->terminated(['campaign_code'=>'SP-TEST','branch_name_en'=>'Test Branch','termination_reason'=>'Agreement ended early','creator_email'=>'staff@example.com']);
  self::assertTrue($result['ok']);self::assertStringContainsString('removed from delivery',$transport->message['text']);self::assertStringContainsString('does not determine billing',$transport->message['html']);self::assertStringNotContainsString('refund approved',$transport->message['text']);
 }
}
