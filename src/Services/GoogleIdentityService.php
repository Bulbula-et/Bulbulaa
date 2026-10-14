<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Core\Config;

/** Server-side Google Identity Services ID-token verifier (C-30). */
final class GoogleIdentityService
{
    public function verifyIdToken(string $credential): array
    {
        $credential = trim($credential);
        $clientId   = trim((string) Config::get('auth.google_client_id', ''));
        if ($clientId === '') {
            return ['ok' => false, 'error' => 'Google Sign-In is not configured yet.'];
        }
        if (substr_count($credential, '.') !== 2 || strlen($credential) > 10000) {
            return ['ok' => false, 'error' => 'Invalid Google identity credential.'];
        }

        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credential);
        $ch  = curl_init($url);
        if ($ch === false) return ['ok' => false, 'error' => 'Google verification could not start.'];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'Bulbula.et/1.0 GoogleIdentityVerifier',
        ]);
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $http !== 200) {
            return ['ok' => false, 'error' => $err !== '' ? 'Google verification network error.' : 'Google rejected or expired this credential.'];
        }
        $claims = json_decode((string) $body, true);
        if (!is_array($claims)) return ['ok' => false, 'error' => 'Invalid response from Google.'];

        $issuerOk = in_array((string) ($claims['iss'] ?? ''), ['accounts.google.com', 'https://accounts.google.com'], true);
        $audOk    = hash_equals($clientId, (string) ($claims['aud'] ?? ''));
        $expOk    = (int) ($claims['exp'] ?? 0) > time();
        $emailOk  = filter_var($claims['email'] ?? '', FILTER_VALIDATE_EMAIL) && in_array($claims['email_verified'] ?? false, [true, 'true', '1', 1], true);
        $subOk    = preg_match('/^[0-9]{5,255}$/', (string) ($claims['sub'] ?? '')) === 1;

        if (!$issuerOk || !$audOk || !$expOk || !$emailOk || !$subOk) {
            return ['ok' => false, 'error' => 'Google credential failed issuer, audience, expiry, or verified-email validation.'];
        }

        return ['ok' => true, 'identity' => [
            'sub' => (string) $claims['sub'],
            'email' => mb_strtolower((string) $claims['email'], 'UTF-8'),
            'name' => trim((string) ($claims['name'] ?? explode('@', (string) $claims['email'])[0])),
            'picture' => (string) ($claims['picture'] ?? ''),
        ]];
    }
}
