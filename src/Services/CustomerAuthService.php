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
 * - Telegram launch data authenticates only after server verification and explicit one-to-one linking.
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
        $telegramId = Session::get('_telegram_user_id');
        if (is_string($telegramId) && (new \Bulbula\Repositories\CustomerTelegramLinkRepository())->customerIdFor($telegramId) !== (int)$id) {
            (new AccountSessionService())->revokeCurrent();
            Session::forget('_telegram_user_id');
            Session::forget('customer_id');
            return null;
        }
        if (!(new AccountSessionService())->ensure('CUSTOMER', (int) $id)) {
            Session::forget('customer_id');
            return null;
        }
        $customer = $this->customerRepo->findById((int)$id);
        if (is_string($telegramId) && ($customer === null || ($customer['account_status'] ?? '') !== 'ACTIVE')) {
            (new AccountSessionService())->revokeCurrent();
            Session::forget('_telegram_user_id');
            Session::forget('customer_id');
            return null;
        }
        return $customer;
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
        if (!$valid) {
            if($customer!==null)$this->auditService->logCustomerAction((int)$customer['id'],'CUSTOMER_LOGIN_FAILED','customer',(int)$customer['id'],['method'=>'PASSWORD']);
            return ['ok' => false, 'error' => 'The email or password is incorrect.'];
        }
        if (($customer['account_status'] ?? '') === 'PENDING_DELETION') {
            $effective = strtotime((string)($customer['deletion_effective_at'] ?? '')) ?: 0;
            if ($effective > time()) {
                Session::regenerate(); Session::set('restore_customer_id',(int)$customer['id']); Session::set('restore_verified_at',time());
                return ['ok'=>true,'restore_required'=>true,'customer'=>$customer];
            }
            $this->customerRepo->permanentlyErasePendingDeletion((int)$customer['id']);
            (new CustomerLifecycleMailer())->deleted((string)$customer['email']);
            return ['ok'=>false,'error'=>'The email or password is incorrect.'];
        }
        if (($customer['account_status'] ?? '') !== 'ACTIVE') return ['ok' => false, 'error' => 'The email or password is incorrect.'];
        if ((new CustomerPasswordPolicy())->needsRehash((string) $customer['password_hash'])) {
            $hash = (new CustomerPasswordPolicy())->hash($password);
            if (is_string($hash)) $this->customerRepo->updatePassword((int) $customer['id'], $hash);
        }
        Session::regenerate();
        Session::forget('_telegram_user_id');
        Session::set('customer_id', (int) $customer['id']);
        (new AccountSessionService())->begin('CUSTOMER', (int) $customer['id']);
        $this->auditService->logCustomerAction((int)$customer['id'],'CUSTOMER_LOGIN_SUCCESS','customer',(int)$customer['id'],['method'=>'PASSWORD']);
        return ['ok' => true, 'customer' => $this->customerRepo->findById((int) $customer['id'])];
    }

    public function changePassword(array $customer, string $currentPassword, string $newPassword): array
    {
        $existingHash = (string) ($customer['password_hash'] ?? '');
        if ($existingHash !== '' && !password_verify($currentPassword, $existingHash)) return ['ok' => false, 'error' => 'Your current password is incorrect.'];
        $error = $this->passwordValidationError($newPassword);
        if ($error !== null) return ['ok' => false, 'error' => $error];
        $hash = (new CustomerPasswordPolicy())->hash($newPassword);
        if (!is_string($hash)) return ['ok' => false, 'error' => 'The new password could not be secured.'];
        $this->customerRepo->updatePassword((int) $customer['id'], $hash);
        Session::regenerate();
        Session::forget('_telegram_user_id');
        Session::set('customer_id', (int) $customer['id']);
        return ['ok' => true];
    }

    private function passwordValidationError(string $password): ?string
    {
        return (new CustomerPasswordPolicy())->error($password);
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
        if (($customer['account_status'] ?? '') === 'PENDING_DELETION') {
            $effective=strtotime((string)($customer['deletion_effective_at']??''))?:0;
            if($effective>time()){Session::regenerate();Session::set('restore_customer_id',(int)$customer['id']);Session::set('restore_verified_at',time());return ['ok'=>true,'restore_required'=>true,'customer'=>$customer];}
            $this->customerRepo->permanentlyErasePendingDeletion((int)$customer['id']);(new CustomerLifecycleMailer())->deleted((string)$customer['email']);return ['ok'=>false,'error'=>'This account is no longer available.'];
        }

        Session::regenerate();
        Session::forget('_telegram_user_id');
        Session::set('customer_id', (int) $customer['id']);
        (new AccountSessionService())->begin('CUSTOMER', (int) $customer['id']);
        $this->auditService->logCustomerAction((int)$customer['id'],'CUSTOMER_LOGIN_SUCCESS','customer',(int)$customer['id'],['method'=>'EMAIL_OTP']);

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

    public function beginEmailChange(array $customer, string $newRawEmail, string $currentPassword, string $clientIpHash): array
    {
        $newEmail = mb_strtolower(trim($newRawEmail), 'UTF-8');
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid new email address.'];
        if (hash_equals(mb_strtolower((string) $customer['email'], 'UTF-8'), $newEmail)) return ['ok' => false, 'error' => 'Enter an email different from your current address.'];
        if ($this->customerRepo->emailBelongsToAnotherCustomer($newEmail, (int) $customer['id'])) return ['ok' => false, 'error' => 'That email address cannot be used.'];
        $hash = (string) ($customer['password_hash'] ?? '');
        if ($hash !== '' && !password_verify($currentPassword, $hash)) return ['ok' => false, 'error' => 'Current password is incorrect.'];
        $result = $this->issueEmailOtp((string) $customer['email'], $clientIpHash, 'SENSITIVE_ACTION');
        if (!empty($result['ok'])) { Session::set('email_change_new_email', $newEmail); Session::set('email_change_stage', 'CURRENT'); Session::set('email_change_requested_at', time()); }
        return $result;
    }

    public function verifyCurrentEmailForChange(array $customer, string $code, string $clientIpHash): array
    {
        $newEmail = (string) Session::get('email_change_new_email', '');
        if ((string) Session::get('email_change_stage', '') !== 'CURRENT' || (int) Session::get('email_change_requested_at', 0) < time() - 600) return ['ok' => false, 'error' => 'Email change confirmation expired. Start again.'];
        $result = $this->verifyPurposeOtp((string) $customer['email'], $code, 'SENSITIVE_ACTION');
        if (!$result['ok']) return $result;
        $delivery = $this->issueEmailOtp($newEmail, $clientIpHash, 'VERIFY_EMAIL');
        if (!empty($delivery['ok'])) Session::set('email_change_stage', 'NEW');
        return $delivery;
    }

    public function completeEmailChange(array $customer, string $code): array
    {
        $newEmail = (string) Session::get('email_change_new_email', '');
        if ((string) Session::get('email_change_stage', '') !== 'NEW' || $newEmail === '') return ['ok' => false, 'error' => 'Email change confirmation expired. Start again.'];
        $result = $this->verifyPurposeOtp($newEmail, $code, 'VERIFY_EMAIL');
        if (!$result['ok']) return $result;
        if ($this->customerRepo->emailBelongsToAnotherCustomer($newEmail, (int) $customer['id'])) return ['ok' => false, 'error' => 'That email address cannot be used.'];
        $this->customerRepo->updateEmail((int) $customer['id'], $newEmail);
        Session::forget('email_change_new_email'); Session::forget('email_change_stage'); Session::forget('email_change_requested_at');
        Session::regenerate();
        return ['ok' => true, 'email' => $newEmail];
    }

    private function verifyPurposeOtp(string $rawEmail, string $submittedCode, string $purpose): array
    {
        $email=mb_strtolower(trim($rawEmail),'UTF-8'); $code=preg_replace('/\D/','',$submittedCode);
        if(strlen((string)$code)!==6)return ['ok'=>false,'error'=>'Enter the six-digit confirmation code.'];
        $row=$this->customerRepo->findLatestActiveOtp($email,$purpose); if($row===null)return ['ok'=>false,'error'=>'The confirmation code expired. Request a new one.'];
        if((int)$row['attempt_count']>=(int)Config::get('security.otp_max_attempts',5))return ['ok'=>false,'error'=>'Maximum confirmation attempts exceeded.'];
        $this->customerRepo->incrementOtpAttempts((int)$row['id']); $expected=hash_hmac('sha256',$email.':'.$code,(string)Config::get('app.secret_key','bulbula_secret'));
        if(!hash_equals((string)$row['code_hmac_sha256'],$expected))return ['ok'=>false,'error'=>'The confirmation code is incorrect.'];
        $this->customerRepo->markOtpConsumed((int)$row['id']); return ['ok'=>true];
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
        $hash = (new CustomerPasswordPolicy())->hash($newPassword);
        if (!is_string($hash)) return ['ok' => false, 'error' => 'The new password could not be secured.'];
        $this->customerRepo->updatePassword($customerId, $hash);
        Session::forget('password_reset_customer_id');
        Session::forget('password_reset_verified_at');
        Session::regenerate();
        Session::forget('_telegram_user_id');
        Session::set('customer_id', $customerId);
        (new AccountSessionService())->begin('CUSTOMER', $customerId);
        (new AccountSessionService())->revokeOthers('CUSTOMER',$customerId);
        $resetCustomer=$this->customerRepo->findById($customerId);if($resetCustomer!==null)(new CustomerSecurityMailer())->passwordChanged((string)$resetCustomer['email'],'reset');
        $this->auditService->logCustomerAction($customerId,'CUSTOMER_PASSWORD_RESET','customer',$customerId,['other_sessions_revoked'=>true]);
        return ['ok' => true, 'customer' => $this->customerRepo->findById($customerId)];
    }

    /** Resolve a cryptographically verified Google identity without authenticating this browser. */
    public function resolveVerifiedGoogleIdentity(string $email, string $displayName, string $googleSubId, string $surface = 'WEB'): array
    {
        return $this->customerRepo->findOrCreateByEmail($email, $displayName, $googleSubId, $surface);
    }

    /** Existing Web callback: verifies identity, then signs in the same browser. */
    public function signInWithVerifiedGoogleIdentity(string $email, string $displayName, string $googleSubId, string $surface = 'WEB'): array
    {
        $customer = $this->resolveVerifiedGoogleIdentity($email, $displayName, $googleSubId, $surface);
        return $this->signInExistingGoogleCustomer((int) $customer['id']);
    }

    /** Only invoke after a verified Google callback or a session-bound consumed handoff. */
    public function signInExistingGoogleCustomer(int $customerId): array
    {
        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) return ['account_status' => 'DELETED'];
        if (($customer['account_status'] ?? '') === 'PENDING_DELETION') {
            $effective=strtotime((string)($customer['deletion_effective_at']??''))?:0;
            if($effective>time()){Session::regenerate();Session::set('restore_customer_id',(int)$customer['id']);Session::set('restore_verified_at',time());$customer['_restore_required']=true;return $customer;}
            $this->customerRepo->permanentlyErasePendingDeletion((int)$customer['id']);(new CustomerLifecycleMailer())->deleted((string)$customer['email']);$customer['account_status']='DELETED';return $customer;
        }
        if (($customer['account_status'] ?? '') !== 'ACTIVE') return $customer;
        Session::regenerate();
        Session::forget('_telegram_user_id');
        Session::set('customer_id', (int) $customer['id']);
        (new AccountSessionService())->begin('CUSTOMER', (int) $customer['id']);
        $this->auditService->logCustomerAction((int)$customer['id'],'CUSTOMER_LOGIN_SUCCESS','customer',(int)$customer['id'],['method'=>'GOOGLE']);
        return $customer;
    }

    /** Telegram-only sign-in: never creates an account or restores a pending deletion. */
    public function signInExistingTelegramCustomer(int $customerId, string $telegramId): bool
    {
        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null || ($customer['account_status'] ?? '') !== 'ACTIVE'
            || (new \Bulbula\Repositories\CustomerTelegramLinkRepository())->customerIdFor($telegramId) !== $customerId) return false;
        Session::regenerate();
        Session::set('customer_id', $customerId);
        Session::set('_telegram_user_id', $telegramId);
        (new AccountSessionService())->begin('CUSTOMER', $customerId);
        $this->auditService->logCustomerAction($customerId, 'CUSTOMER_LOGIN_SUCCESS', 'customer', $customerId, ['method'=>'TELEGRAM_VERIFIED_LINK']);
        return true;
    }

    public function pendingRestoreCustomer(): ?array
    {
        $id=(int)Session::get('restore_customer_id',0);$verified=(int)Session::get('restore_verified_at',0);
        if($id<=0||$verified<time()-900)return null;
        $customer=$this->customerRepo->findById($id);return $customer!==null&&($customer['account_status']??'')==='PENDING_DELETION'?$customer:null;
    }

    public function restorePendingAccount(): array
    {
        $customer=$this->pendingRestoreCustomer();if($customer===null)return ['ok'=>false,'error'=>'Your verified recovery session expired. Sign in again.'];
        $this->customerRepo->restorePendingDeletion((int)$customer['id']);$this->auditService->logCustomerAction((int)$customer['id'],'CUSTOMER_ACCOUNT_RESTORED','customer',(int)$customer['id'],['self_service'=>true]);(new CustomerLifecycleMailer())->restored((string)$customer['email']);Session::forget('restore_customer_id');Session::forget('restore_verified_at');Session::regenerate();Session::set('customer_id',(int)$customer['id']);(new AccountSessionService())->begin('CUSTOMER',(int)$customer['id']);return ['ok'=>true,'customer'=>$this->customerRepo->findById((int)$customer['id'])];
    }

    public function permanentlyDeletePendingAccount(): array
    {
        $customer=$this->pendingRestoreCustomer();if($customer===null)return ['ok'=>false,'error'=>'Your verified recovery session expired. Sign in again.'];
        $email=(string)$customer['email'];$this->auditService->logCustomerAction((int)$customer['id'],'CUSTOMER_PERMANENT_DELETION_CONFIRMED','customer',(int)$customer['id'],['self_service'=>true]);$this->customerRepo->permanentlyErasePendingDeletion((int)$customer['id']);(new CustomerLifecycleMailer())->deleted($email);Session::forget('restore_customer_id');Session::forget('restore_verified_at');Session::reset();return ['ok'=>true];
    }

    public function logout(): void
    {
        $customer = $this->currentCustomer();
        if ($customer !== null) $this->auditService->logCustomerAction((int) $customer['id'], 'CUSTOMER_LOGOUT', 'customer', (int) $customer['id']);
        (new AccountSessionService())->revokeCurrent();
        Session::reset();
        Session::forget('_account_session_token');
    }
}
