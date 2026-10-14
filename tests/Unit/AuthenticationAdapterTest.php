<?php
declare(strict_types=1);

use Bulbula\Core\Mailer;
use Bulbula\Services\GoogleIdentityService;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

final class AuthenticationAdapterTest extends TestCase
{
    public function testMatureVendorLibrariesAreInstalled(): void
    {
        self::assertTrue(class_exists(PHPMailer::class));
        self::assertTrue(class_exists(Google\Client::class));
    }

    public function testGoogleVerifierRejectsMalformedCredentialWithoutNetwork(): void
    {
        $result = (new GoogleIdentityService())->verifyIdToken('not-a-jwt');
        self::assertFalse($result['ok']);
    }

    public function testMailerRejectsInvalidRecipientBeforeTransport(): void
    {
        $result = Mailer::send('not-an-email', 'Subject', '<p>Body</p>', 'Body');
        self::assertFalse($result['ok']);
        self::assertStringContainsString('Invalid recipient', $result['error']);
    }
}
