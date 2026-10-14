<?php
declare(strict_types=1);

use Bulbula\Services\StaffTwoFactorService;
use PHPUnit\Framework\TestCase;
use RobThree\Auth\Providers\Qr\IQRCodeProvider;
use RobThree\Auth\TwoFactorAuth;

final class StaffTwoFactorServiceTest extends TestCase
{
    public function testSecretEncryptionRoundTripAndTotpVerification(): void
    {
        $service = new StaffTwoFactorService();
        $secret = $service->createSecret();
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);

        $encrypted = $service->encryptSecret($secret);
        self::assertNotSame($secret, $encrypted);
        self::assertSame($secret, $service->decryptSecret($encrypted));

        $provider = new class implements IQRCodeProvider {
            public function getQRCodeImage(string $qrText, int $size): string { return ''; }
            public function getMimeType(): string { return 'text/plain'; }
        };
        $code = (new TwoFactorAuth($provider, 'Bulbula.et Staff', 6, 30))->getCode($secret);
        self::assertTrue($service->verify($secret, $code));
        self::assertFalse($service->verify($secret, '000000') && $code !== '000000');
    }

    public function testProvisioningUriContainsIssuerAndAccount(): void
    {
        $service = new StaffTwoFactorService();
        $uri = $service->provisioningUri('admin@bulbula.et', $service->createSecret());
        self::assertStringStartsWith('otpauth://totp/', $uri);
        self::assertStringContainsString('admin%40bulbula.et', $uri);
        self::assertStringContainsString('issuer=Bulbula.et%20Staff', $uri);
    }
}
