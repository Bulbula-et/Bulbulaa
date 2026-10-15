<?php
declare(strict_types=1);

use Bulbula\Contracts\MailTransport;
use Bulbula\Services\CustomerReviewMailer;
use PHPUnit\Framework\TestCase;

final class CustomerReviewMailerTest extends TestCase
{
    public function testSubmissionNotificationExcludesReviewTextAndPrivateNotes(): void
    {
        $transport = new class implements MailTransport {
            public array $message=[];
            public function send(string $to,string $subject,string $html,string $text=''): array { $this->message=compact('to','subject','html','text'); return ['ok'=>true,'transport'=>'test']; }
        };
        $mailer = new CustomerReviewMailer($transport);
        $result = $mailer->send([
            'customer_email'=>'customer@example.com','branch_name_en'=>'Bole Branch','business_name_en'=>'Example Business',
            'review_text'=>'PRIVATE REVIEW BODY','moderation_notes'=>'PRIVATE STAFF NOTE'
        ], 'SUBMITTED');
        self::assertTrue($result['ok']);
        self::assertStringContainsString('pending moderation', $transport->message['text']);
        self::assertStringNotContainsString('PRIVATE REVIEW BODY', $transport->message['html']);
        self::assertStringNotContainsString('PRIVATE STAFF NOTE', $transport->message['html']);
    }

    public function testModerationTemplatesCoverPublicationRejectionAndRemoval(): void
    {
        $mailer = new CustomerReviewMailer(new class implements MailTransport { public function send(string $to,string $subject,string $html,string $text=''): array{return ['ok'=>true];} });
        $context=['branch_name_en'=>'Kazanchis','business_name_en'=>'Example','moderation_reason_code'=>'POLICY_VIOLATION'];
        foreach (['PUBLISHED','REJECTED','REMOVED_BY_STAFF','EDITED'] as $event) self::assertNotNull($mailer->message($context,$event));
        self::assertStringContainsString('policy violation', $mailer->message($context,'REJECTED')['text']);
    }

    public function testUnsupportedEventFailsBeforeTransport(): void
    {
        $mailer = new CustomerReviewMailer(new class implements MailTransport { public function send(string $to,string $subject,string $html,string $text=''): array{throw new RuntimeException('Transport must not run.');} });
        self::assertFalse($mailer->send(['customer_email'=>'a@example.com'],'UNKNOWN')['ok']);
    }
}
