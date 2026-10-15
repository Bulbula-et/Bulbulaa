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
        if (!(new AccountSessionService())->ensure('CUSTOMER', (int) $id)) {
            Session::forget('customer_id');
            return null;
        }
        return $this->customerRepo->findById((int) $id);
    }

    public function registerWithPassword(string $rawEmail, string $displayName, string $password, string $surface = 'WEB'): array
    {
        $email = mb_strtolower(trim($rawEmail), 'UTF-8');
        $name = trim($displayName);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Please enter a valid email address.'];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) return ['ok' => false, 'error' => 'Your name must be between 2 and 100 characters.'];
        $passwordError = $this->passwordValidationError($password);
        if ($passwordError !== null) return ['ok' => false, 'error' => $passwordError];
        if ($this->customerRepo->findByEmail($email) !== null) return ['ok' => false, 'error' => 'An account already exists for this email. Sign in or reset your password.'];

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash)) return ['ok' => false, 'error' => 'Your account could not be secured. Please try again.'];
        $customer = $this->customerRepo->createWithPassword($email, $name, $hash, $surface);
        Session::regenerate();
        Session::set('customer_id', (int) $customer['id']);
        (new AccountSessionService())->begin('CUSTOMER', (int) $customer['id']);
        return ['ok' => true, 'customer' => $customer];
    }

    public function signInWithPassword(string $rawEmail, string $password, string $clientIpHash): array
    {
        $email = mb_strtolower(trim($rawEmail), 'UTF-8');
        if (!RateLimiter::allow('customer_login:' . md5($email . ':' . $clientIpHash), 8, 900)) {
            return ['ok' => false, 'error' => 'Too many sign-in attempts. Please wait before trying again.'];
        }
        $customer = $this->customerRepo->findByEmail($email);
        $valid = $customer !== null && is_string($customer['password_hash'] ?? null)
            && password_verify($password, (string) $customer['password_hash']);
        if (!$valid || ($customer['account_status'] ?? '') !== 'ACTIVE') {
            return ['ok' => false, 'error' => 'The email or password is incorrect.'];
        }
        if (password_needs_rehash((string) $customer['password_hash'], PASSWORD_DEFAULT)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if (is_string($hash)) $this->customerRepo->updatePassword((int) $customer['id'], $hash);
        }
        Session::regenerate();
        Session::set('customer_id', (int) $customer['id']);
        (new AccountSessionService())->begin('CUSTOMER', (int) $customer['id']);
        return ['ok' => true, 'customer' => $this->customerRepo->findById((int) $customer['id'])];
    }

    public function changePassword(array $customer, string $currentPassword, string $newPassword): array
    {
        $existingHash = (string) ($customer['password_hash'] ?? '');
        if ($existingHash !== '' && !password_verify($currentPassword, $existingHash)) return ['ok' => false, 'error' => 'Your current password is incorrect.'];
        $error = $this->passwordValidationError($newPassword);
        if ($error !== null) return ['ok' => false, 'error' => $error];
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!is_string($hash)) return ['ok' => false, 'error' => 'The new password could not be secured.'];
        $this->customerRepo->updatePassword((int) $customer['id'], $hash);
        Session::regenerate();
        Session::set('customer_id', (int) $customer['id']);
        return ['ok' => true];
    }

    private function passwordValidationError(string $password): ?string
    {
        if (strlen($password) < 12 || strlen($password) > 128) return 'Use a password between 12 and 128 characters.';
        if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Include an uppercase letter, a lowercase letter, and a number.';
        }
        return null;
    }

    /** Generates and stores a short-lived email verification code. */
    public function issueEmailOtp(string $rawEmail, string $clientIpHash, string $purpose = 'SIGN_IN'): array
    {
        $email = mb_strtolower(trim($rawEmail), 'UTF-8');
        if (!in_array($purpose, ['SIGN_IN', 'VERIFY_EMAIL', 'PASSWORD_RESET', 'SENSITIVE_ACTION'], true)) {
            return ['ok' => false, 'error' => 'Unsupported verification request.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Please enter a valid email address.'];
        }

        if (!RateLimiter::allow('otp_req:' . md5($purpose . ':' . $email . ':' . $clientIpHash), 5, 600)) {
            return ['ok' => false, 'error' => 'Too many code requests. Please wait a few minutes before requesting another code.'];
        }

        $code   = (string) random_int(100000, 999999);
        $secret = (string) Config::get('app.secret_key', 'bulbula_secret');
        $hmac   = hash_hmac('sha256', $email . ':' . $code, $secret);
        $ttl    = (int) Config::get('security.otp_expiry_seconds', 600);

        $isDev = Config::previewFeaturesEnabled();
        $minutes = max(1, (int) ceil($ttl / 60));
        $safeCode = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $action = match ($purpose) { 'PASSWORD_RESET' => 'reset your password', 'VERIFY_EMAIL' => 'verify your email', 'SENSITIVE_ACTION' => 'confirm a sensitive account action', default => 'sign in' };
        $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:28px;color:#1f2937">'
              . '<div style="color:#EA6A15;font-weight:800;font-size:20px">Bulbula.et</div>'
              . '<h1 style="font-size:24px;margin:20px 0 8px">Your verification code</h1>'
              . '<p>Use this one-time code to ' . $action . '. It expires in ' . $minutes . ' minutes.</p>'
              . '<div style="font-size:34px;letter-spacing:8px;font-weight:800;background:#FFF5EB;border:1px solid #FFEBDA;border-radius:14px;padding:18px;text-align:center">' . $safeCode . '</div>'
              . '<p style="font-size:13px;color:#6b7280">If you did not request this code, you can safely ignore this email. Never share the code with anyone.</p></div>';
        $text = "Your Bulbula.et code to {$action} is {$code}. It expires in {$minutes} minutes. Never share this code.";

        $mail = Mailer::send($email, $code . ' is your Bulbula.et verification code', $html, $text);
        if (!$mail['ok'] && !$isDev) {
            return ['ok' => false, 'error' => 'We could not deliver your verification email. Please try again later.'];
        }

        // Persist only after successful production delivery; development preview remains usable without SMTP credentials.
        $this->customerRepo->storeOtpToken($email, $hmac, $clientIpHash, $ttl, $purpose);

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
        (new AccountSessionService())->begin('CUSTOMER', (int) $customer['id']);

        return ['ok' => true, 'customer' => $customer];
    }

    public function issueEmailVerification(array $customer, string $clientIpHash): array
    {
        if (!empty($customer['email_verified_at'])) return ['ok' => true, 'already_verified' => true];
        return $this->issueEmailOtp((string) $customer['email'], $clientIpHash, 'VERIFY_EMAIL');
    }

    public function verifyCustomerEmail(array $customer, string $submittedCode): array
    {
        if (!empty($customer['email_verified_at'])) return ['ok' => true];
        $email = mb_strtolower(trim((string) $customer['email']), 'UTF-8');
        $code = preg_replace('/\D/', '', $submittedCode);
        if (strlen((string) $code) !== 6) return ['ok' => false, 'error' => 'Enter the six-digit verification code.'];
        $otpRow = $this->customerRepo->findLatestActiveOtp($email, 'VERIFY_EMAIL');
        if ($otpRow === null) return ['ok' => false, 'error' => 'The verification code has expired. Request a new code.'];
        $maxAttempts = (int) Config::get('security.otp_max_attempts', 5);
        if ((int) $otpRow['attempt_count'] >= $maxAttempts) return ['ok' => false, 'error' => 'Maximum verification attempts exceeded. Request a new code.'];
        $this->customerRepo->incrementOtpAttempts((int) $otpRow['id']);
        $secret = (string) Config::get('app.secret_key', 'bulbula_secret');
        $expected = hash_hmac('sha256', $email . ':' . $code, $secret);
        if (!hash_equals((string) $otpRow['code_hmac_sha256'], $expected)) return ['ok' => false, 'error' => 'The verification code is incorrect.'];
        $this->customerRepo->markOtpConsumed((int) $otpRow['id']);
        $this->customerRepo->markEmailVerified((int) $customer['id']);
        return ['ok' => true];
    }

    public function issueSensitiveActionOtp(array $customer, string $clientIpHash, string $action): array
    {
        if (!in_array($action, ['DATA_ACCESS', 'ACCOUNT_DELETION'], true)) return ['ok' => false, 'error' => 'Unsupported account action.'];
        $result = $this->issueEmailOtp((string) $customer['email'], $clientIpHash, 'SENSITIVE_ACTION');
        if (!empty($result['ok'])) {
            Session::set('sensitive_action_type', $action);
            Session::set('sensitive_action_requested_at', time());
        }
        return $result;
    }

    public function verifySensitiveActionOtp(array $customer, string $submittedCode): array
    {
        $email = mb_strtolower(trim((string) $customer['email']), 'UTF-8');
        $code = preg_replace('/\D/', '', $submittedCode);
        $requestedAt = (int) Session::get('sensitive_action_requested_at', 0);
        if ($requestedAt < time() - 600) return ['ok' => false, 'error' => 'The confirmation request expired. Start again.'];
        if (strlen((string) $code) !== 6) return ['ok' => false, 'error' => 'Enter the six-digit confirmation code.'];
        $otpRow = $this->customerRepo->findLatestActiveOtp($email, 'SENSITIVE_ACTION');
        if ($otpRow === null) return ['ok' => false, 'error' => 'The confirmation code expired. Start again.'];
        $maxAttempts = (int) Config::get('security.otp_max_attempts', 5);
        if ((int) $otpRow['attempt_count'] >= $maxAttempts) return ['ok' => false, 'error' => 'Maximum confirmation attempts exceeded.'];
        $this->customerRepo->incrementOtpAttempts((int) $otpRow['id']);
        $secret = (string) Config::get('app.secret_key', 'bulbula_secret');
        $expected = hash_hmac('sha256', $email . ':' . $code, $secret);
        if (!hash_equals((string) $otpRow['code_hmac_sha256'], $expected)) return ['ok' => false, 'error' => 'The confirmation code is incorrect.'];
        $this->customerRepo->markOtpConsumed((int) $otpRow['id']);
        Session::set('sensitive_action_confirmed_at', time());
        Session::regenerate();
        return ['ok' => true, 'action' => Session::get('sensitive_action_type')];
    }

    public function issuePasswordResetOtp(string $rawEmail, string $clientIpHash): array
    {
        $email = mb_strtolower(trim($rawEmail), 'UTF-8');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => true, 'email' => $email];
        $customer = $this->customerRepo->findByEmail($email);
        if ($customer === null || ($customer['account_status'] ?? '') !== 'ACTIVE') return ['ok' => true, 'email' => $email];
        return $this->issueEmailOtp($email, $clientIpHash, 'PASSWORD_RESET');
    }

    public function verifyPasswordResetOtp(string $rawEmail, string $submittedCode): array
    {
        $email = mb_strtolower(trim($rawEmail), 'UTF-8');
        $code = preg_replace('/\D/', '', $submittedCode);
        if ($email === '' || strlen((string) $code) !== 6) return ['ok' => false, 'error' => 'Enter the 6-digit password recovery code.'];
        $otpRow = $this->customerRepo->findLatestActiveOtp($email, 'PASSWORD_RESET');
        if ($otpRow === null) return ['ok' => false, 'error' => 'The recovery code has expired. Request a new code.'];
        $maxAttempts = (int) Config::get('security.otp_max_attempts', 5);
        if ((int) $otpRow['attempt_count'] >= $maxAttempts) return ['ok' => false, 'error' => 'Maximum recovery attempts exceeded. Request a new code.'];
        $this->customerRepo->incrementOtpAttempts((int) $otpRow['id']);
        $secret = (string) Config::get('app.secret_key', 'bulbula_secret');
        $expected = hash_hmac('sha256', $email . ':' . $code, $secret);
        if (!hash_equals((string) $otpRow['code_hmac_sha256'], $expected)) return ['ok' => false, 'error' => 'The recovery code is incorrect.'];
        $customer = $this->customerRepo->findByEmail($email);
        if ($customer === null || ($customer['account_status'] ?? '') !== 'ACTIVE') return ['ok' => false, 'error' => 'This account cannot be recovered.'];
        $this->customerRepo->markOtpConsumed((int) $otpRow['id']);
        Session::regenerate();
        Session::set('password_reset_customer_id', (int) $customer['id']);
        Session::set('password_reset_verified_at', time());
        return ['ok' => true];
    }

    public function completePasswordReset(string $newPassword): array
    {
        $customerId = (int) Session::get('password_reset_customer_id', 0);
        $verifiedAt = (int) Session::get('password_reset_verified_at', 0);
        if ($customerId <= 0 || $verifiedAt < time() - 900) return ['ok' => false, 'error' => 'Your password recovery session has expired.'];
        $error = $this->passwordValidationError($newPassword);
        if ($error !== null) return ['ok' => false, 'error' => $error];
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!is_string($hash)) return ['ok' => false, 'error' => 'The new password could not be secured.'];
        $this->customerRepo->updatePassword($customerId, $hash);
        Session::forget('password_reset_customer_id');
        Session::forget('password_reset_verified_at');
        Session::regenerate();
        Session::set('customer_id', $customerId);
        (new AccountSessionService())->begin('CUSTOMER', $customerId);
        return ['ok' => true, 'customer' => $this->customerRepo->findById($customerId)];
    }

    /** Server-side Google Sign-In callback handler. */
    public function signInWithVerifiedGoogleIdentity(string $email, string $displayName, string $googleSubId, string $surface = 'WEB'): array
    {
        $customer = $this->customerRepo->findOrCreateByEmail($email, $displayName, $googleSubId, $surface);
        Session::regenerate();
        Session::set('customer_id', (int) $customer['id']);
        (new AccountSessionService())->begin('CUSTOMER', (int) $customer['id']);
        return $customer;
    }

    public function logout(): void
    {
        Session::reset();
    }
}
