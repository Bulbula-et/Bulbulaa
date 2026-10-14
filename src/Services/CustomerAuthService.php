<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Core\Config;
use Bulbula\Core\Mailer;
use Bulbula\Core\RateLimiter;
use Bulbula\Core\Session;
use Bulbula\Repositories\CustomerRepository;

/**
 * Customer Password-Free Authentication & Unified Bulbula Identity Service (C-30, C-31, C-32).
 *
 * Rules:
 * - Supports Google Auth (C-30) and Email One-Time Code (C-31).
 * - OTP codes are short-lived (10m), single-use, rate-limited, and stored as HMAC-SHA256 hashes.
 * - Telegram Mini App launch context is never trusted as Customer identity (C-32).
 */
final class CustomerAuthService
{
    public function __construct(
        private readonly CustomerRepository $customerRepo = new CustomerRepository(),
        private readonly AuditService $auditService = new AuditService()
    ) {}

    public function currentCustomer(): ?array
    {
        $id = Session::get('customer_id');
        if (!is_int($id) && !is_numeric($id)) {
            return null;
        }
        return $this->customerRepo->findById((int) $id);
    }

    /**
     * C-31 Generates a 6-digit Email OTP, stores its HMAC-SHA256 hash, and returns status.
     * In development/preview mode, returns `dev_preview_otp` so the tester can sign in without external SMTP.
     */
    public function issueEmailOtp(string $rawEmail, string $clientIpHash): array
    {
        $email = mb_strtolower(trim($rawEmail), 'UTF-8');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Please enter a valid email address.'];
        }

        if (!RateLimiter::allow('otp_req:' . md5($email . ':' . $clientIpHash), 5, 600)) {
            return ['ok' => false, 'error' => 'Too many code requests. Please wait a few minutes before requesting another code.'];
        }

        $code   = (string) random_int(100000, 999999);
        $secret = (string) Config::get('app.secret_key', 'bulbula_secret');
        $hmac   = hash_hmac('sha256', $email . ':' . $code, $secret);
        $ttl    = (int) Config::get('security.otp_expiry_seconds', 600);

        $isDev = (string) Config::get('app.env', 'development') !== 'production';
        $minutes = max(1, (int) ceil($ttl / 60));
        $safeCode = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:28px;color:#1f2937">'
              . '<div style="color:#EA6A15;font-weight:800;font-size:20px">Bulbula.et</div>'
              . '<h1 style="font-size:24px;margin:20px 0 8px">Your verification code</h1>'
              . '<p>Use this one-time code to sign in. It expires in ' . $minutes . ' minutes.</p>'
              . '<div style="font-size:34px;letter-spacing:8px;font-weight:800;background:#FFF5EB;border:1px solid #FFEBDA;border-radius:14px;padding:18px;text-align:center">' . $safeCode . '</div>'
              . '<p style="font-size:13px;color:#6b7280">If you did not request this code, you can safely ignore this email. Never share the code with anyone.</p></div>';
        $text = "Your Bulbula.et verification code is {$code}. It expires in {$minutes} minutes. Never share this code.";

        $mail = Mailer::send($email, $code . ' is your Bulbula.et verification code', $html, $text);
        if (!$mail['ok'] && !$isDev) {
            return ['ok' => false, 'error' => 'We could not deliver your verification email. Please try again later.'];
        }

        // Persist only after successful production delivery; development preview remains usable without SMTP credentials.
        $this->customerRepo->storeOtpToken($email, $hmac, $clientIpHash, $ttl);

        return [
            'ok'              => true,
            'email'           => $email,
            'expires_seconds' => $ttl,
            'mail_delivered'  => (bool) $mail['ok'],
            'dev_preview_otp' => $isDev ? $code : null,
        ];
    }

    /**
     * C-31 Verifies the submitted 6-digit OTP against the stored HMAC-SHA256 hash.
     */
    public function verifyEmailOtp(string $rawEmail, string $submittedCode, string $surface = 'WEB'): array
    {
        $email = mb_strtolower(trim($rawEmail), 'UTF-8');
        $code  = preg_replace('/\D/', '', $submittedCode);

        if ($email === '' || strlen((string) $code) !== 6) {
            return ['ok' => false, 'error' => 'Please enter the 6-digit verification code sent to your email.'];
        }

        $otpRow = $this->customerRepo->findLatestActiveOtp($email);
        if ($otpRow === null) {
            return ['ok' => false, 'error' => 'Verification code has expired or was not found. Please request a new code.'];
        }

        $maxAttempts = (int) Config::get('security.otp_max_attempts', 5);
        if ((int) $otpRow['attempt_count'] >= $maxAttempts) {
            return ['ok' => false, 'error' => 'Maximum verification attempts exceeded. Please request a new code.'];
        }

        $this->customerRepo->incrementOtpAttempts((int) $otpRow['id']);

        $secret       = (string) Config::get('app.secret_key', 'bulbula_secret');
        $expectedHmac = hash_hmac('sha256', $email . ':' . $code, $secret);

        if (!hash_equals((string) $otpRow['code_hmac_sha256'], $expectedHmac)) {
            return ['ok' => false, 'error' => 'Incorrect verification code. Please check the digits and try again.'];
        }

        $this->customerRepo->markOtpConsumed((int) $otpRow['id']);
        $customer = $this->customerRepo->findOrCreateByEmail($email, null, null, $surface);

        Session::regenerate();
        Session::set('customer_id', (int) $customer['id']);

        return ['ok' => true, 'customer' => $customer];
    }

    /**
     * C-30 Server-side Google Sign-In callback handler.
     */
    public function signInWithVerifiedGoogleIdentity(string $email, string $displayName, string $googleSubId, string $surface = 'WEB'): array
    {
        $customer = $this->customerRepo->findOrCreateByEmail($email, $displayName, $googleSubId, $surface);
        Session::regenerate();
        Session::set('customer_id', (int) $customer['id']);
        return $customer;
    }

    public function logout(): void
    {
        Session::forget('customer_id');
        Session::regenerate();
    }
}
