<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Core\Config;
use RobThree\Auth\Providers\Qr\IQRCodeProvider;
use RobThree\Auth\TwoFactorAuth;

/**
 * Thin Gate D-45 adapter around robthree/twofactorauth (RFC 6238).
 * Secrets are encrypted at rest with native libsodium secretbox.
 */
final class StaffTwoFactorService
{
    private TwoFactorAuth $totp;

    public function __construct()
    {
        $manualProvider = new class implements IQRCodeProvider {
            public function getQRCodeImage(string $qrText, int $size): string { return ''; }
            public function getMimeType(): string { return 'text/plain'; }
        };
        $this->totp = new TwoFactorAuth($manualProvider, 'Bulbula.et Staff', 6, 30);
    }

    public function createSecret(): string
    {
        return $this->totp->createSecret(160);
    }

    public function provisioningUri(string $email, string $secret): string
    {
        return $this->totp->getQRText($email, $secret);
    }

    public function verify(string $secret, string $code): bool
    {
        $digits = preg_replace('/\D/', '', $code) ?? '';
        return strlen($digits) === 6 && $this->totp->verifyCode($secret, $digits, 1);
    }

    public function encryptSecret(string $secret): string
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new \RuntimeException('The PHP Sodium extension is required for encrypted TOTP storage.');
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($secret, $nonce, $this->encryptionKey());
        return base64_encode($nonce . $cipher);
    }

    public function decryptSecret(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Invalid encrypted TOTP secret.');
        }
        $nonce  = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain  = sodium_crypto_secretbox_open($cipher, $nonce, $this->encryptionKey());
        if ($plain === false) throw new \RuntimeException('TOTP secret could not be decrypted.');
        return $plain;
    }

    private function encryptionKey(): string
    {
        $appSecret = (string) Config::get('app.secret_key', '');
        if (strlen($appSecret) < 32) {
            throw new \RuntimeException('A strong application secret is required for TOTP encryption.');
        }
        return sodium_crypto_generichash('bulbula.staff.totp.v1|' . $appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
