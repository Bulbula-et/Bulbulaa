<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Core\Config;
use Google\Client as GoogleClient;

/**
 * Server-side Google Identity Services ID-token verifier (C-30), backed by
 * Google's official google/apiclient package and cryptographic JWT validation.
 */
final class GoogleIdentityService
{
    public function verifyIdToken(string $credential): array
    {
        $credential = trim($credential);
        $clientId   = trim((string) Config::get('auth.google_client_id', ''));

        if ($clientId === '') {
            return ['ok' => false, 'error' => 'Google Sign-In is not configured yet.'];
        }
        if (!class_exists(GoogleClient::class)) {
            return ['ok' => false, 'error' => 'Google API Client is not installed. Run composer install.'];
        }
        if (substr_count($credential, '.') !== 2 || strlen($credential) > 10000) {
            return ['ok' => false, 'error' => 'Invalid Google identity credential.'];
        }

        try {
            $client = new GoogleClient(['client_id' => $clientId]);
            $claims = $client->verifyIdToken($credential);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Google credential verification failed. Please try again.'];
        }

        if (!is_array($claims)) {
            return ['ok' => false, 'error' => 'Google rejected or expired this credential.'];
        }

        $issuerOk = in_array((string) ($claims['iss'] ?? ''), ['accounts.google.com', 'https://accounts.google.com'], true);
        $audOk    = hash_equals($clientId, (string) ($claims['aud'] ?? ''));
        $expOk    = (int) ($claims['exp'] ?? 0) > time();
        $emailOk  = filter_var($claims['email'] ?? '', FILTER_VALIDATE_EMAIL)
            && in_array($claims['email_verified'] ?? false, [true, 'true', '1', 1], true);
        $subOk    = preg_match('/^[0-9]{5,255}$/', (string) ($claims['sub'] ?? '')) === 1;

        if (!$issuerOk || !$audOk || !$expOk || !$emailOk || !$subOk) {
            return ['ok' => false, 'error' => 'Google credential failed issuer, audience, expiry, or verified-email validation.'];
        }

        return ['ok' => true, 'identity' => [
            'sub'     => (string) $claims['sub'],
            'email'   => mb_strtolower((string) $claims['email'], 'UTF-8'),
            'name'    => trim((string) ($claims['name'] ?? explode('@', (string) $claims['email'])[0])),
            'picture' => (string) ($claims['picture'] ?? ''),
        ]];
    }
}
