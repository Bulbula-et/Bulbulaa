<?php
declare(strict_types=1);
namespace Bulbula\Services;

use Bulbula\Core\Config;
use Bulbula\Core\Database;
use Bulbula\Core\Mailer;
use Bulbula\Core\RateLimiter;
use Bulbula\Core\Session;
use Bulbula\Repositories\CustomerRepository;
use PDOException;

/** Email-proven password registration; no Customer exists until a valid code is consumed. */
final class CustomerRegistrationService
{
    public function __construct(
        private readonly CustomerPasswordPolicy $policy = new CustomerPasswordPolicy(),
        private readonly CustomerRepository $customers = new CustomerRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {}

    public function pending(): ?array
    {
        $pending = Session::get('registration_pending');
        if (!is_array($pending) || !is_string($pending['email'] ?? null) || !is_string($pending['password_hash'] ?? null)) return null;
        if (($pending['expires_at'] ?? 0) < time()) { Session::forget('registration_pending'); return null; }
        return $pending;
    }

    public function begin(string $rawEmail, string $rawName, string $password, string $surface): array
    {
        $email = mb_strtolower(trim($rawEmail), 'UTF-8');
        $name = trim($rawName);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) return ['ok'=>false,'error'=>'Enter a valid email address.'];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) return ['ok'=>false,'error'=>'Your name must contain 2 to 100 characters.'];
        if (($error = $this->policy->error($password)) !== null) return ['ok'=>false,'error'=>$error];
        if ($this->customers->findByEmail($email) !== null) return ['ok'=>false,'error'=>'An account already exists for this email. Sign in or recover your password.'];
        if (!$this->allowSend($email)) return ['ok'=>false,'error'=>'Too many verification requests. Please try again later.'];
        try { $passwordHash = $this->policy->hash($password); }
        catch (\Throwable) { return ['ok'=>false,'error'=>'Your password could not be secured. Please try again.']; }
        $code = (string) random_int(100000, 999999);
        if (!$this->deliver($email, $code)) return ['ok'=>false,'error'=>'We could not send the verification email. Please try again later.'];
        Session::regenerate();
        Session::set('registration_pending', [
            'email'=>$email, 'display_name'=>$name, 'password_hash'=>$passwordHash,
            'surface'=>$surface === 'TELEGRAM_MINI_APP' ? 'TELEGRAM_MINI_APP' : 'WEB',
            'code_hash'=>$this->codeHash($email, $code), 'expires_at'=>time()+600, 'attempts'=>0,
        ]);
        return ['ok'=>true, 'dev_preview_code'=>Config::previewFeaturesEnabled() ? $code : null];
    }

    public function resend(): array
    {
        $pending = $this->pending();
        if ($pending === null) return ['ok'=>false,'error'=>'Your verification session expired. Start registration again.'];
        $email = (string)$pending['email'];
        if (!$this->allowSend($email)) return ['ok'=>false,'error'=>'Too many verification requests. Please try again later.'];
        $code = (string) random_int(100000, 999999);
        if (!$this->deliver($email, $code)) return ['ok'=>false,'error'=>'We could not send a new code. Please try again later.'];
        $pending['code_hash'] = $this->codeHash($email, $code);
        $pending['expires_at'] = time()+600;
        $pending['attempts'] = 0;
        Session::set('registration_pending', $pending);
        return ['ok'=>true, 'dev_preview_code'=>Config::previewFeaturesEnabled() ? $code : null];
    }

    public function verify(string $code): array
    {
        $pending = $this->pending();
        if ($pending === null) return ['ok'=>false,'error'=>'Your verification session expired. Start registration again.'];
        if ((int)$pending['attempts'] >= 5) return ['ok'=>false,'error'=>'Too many incorrect codes. Request a new code.'];
        if (!preg_match('/^[0-9]{6}$/D', $code)) return ['ok'=>false,'error'=>'Enter the six-digit code from the email.'];
        $pending['attempts'] = (int)$pending['attempts'] + 1;
        Session::set('registration_pending', $pending);
        if (!hash_equals((string)$pending['code_hash'], $this->codeHash((string)$pending['email'], $code))) {
            return ['ok'=>false,'error'=>$pending['attempts'] >= 5 ? 'Too many incorrect codes. Request a new code.' : 'That code is incorrect. Check the email or request a new code.'];
        }
        try {
            $customer = Database::transaction(function () use ($pending): array {
                return $this->customers->createVerifiedWithPassword(
                    (string)$pending['email'], (string)$pending['display_name'],
                    (string)$pending['password_hash'], (string)$pending['surface']
                );
            });
        } catch (PDOException $e) {
            if (($e->errorInfo[0] ?? '') !== '23000') throw $e;
            Session::forget('registration_pending');
            return ['ok'=>false,'error'=>'This email already has an account. Sign in instead.'];
        }
        Session::forget('registration_pending');
        Session::forget('registration_dev_preview');
        Session::regenerate();
        Session::set('customer_id', (int)$customer['id']);
        (new AccountSessionService())->begin('CUSTOMER', (int)$customer['id']);
        $this->audit->logCustomerAction((int)$customer['id'], 'CUSTOMER_REGISTERED', 'customer', (int)$customer['id'], ['method'=>'PASSWORD_EMAIL_VERIFIED']);
        return ['ok'=>true, 'customer'=>$customer];
    }

    private function allowSend(string $email): bool
    {
        return RateLimiter::allow('registration-mail:' . hash('sha256', $email), 5, 3600)
            && RateLimiter::allow('registration-session:' . session_id(), 10, 3600);
    }

    private function deliver(string $email, string $code): bool
    {
        $text = "Your Bulbula.et registration code is {$code}. Enter it in the registration screen within 10 minutes. If you did not request this, ignore this email. Never share the code.";
        $fallback = ['subject'=>'Your Bulbula.et registration code','html'=>'','text'=>$text];
        $message = (new EmailTemplateService())->render('registration_code', ['code'=>$code,'minutes'=>'10'], $fallback);
        $mail = $message['html'] === '' ? Mailer::sendText($email, $message['subject'], $message['text']) : Mailer::send($email, $message['subject'], $message['html'], $message['text']);
        // Existing local/CI preview gate; never substitutes for successful production delivery.
        return !empty($mail['ok']) || Config::previewFeaturesEnabled();
    }

    private function codeHash(string $email, string $code): string
    {
        $secret = (string)Config::get('app.secret_key', '');
        if (strlen($secret) < 32) throw new \RuntimeException('Registration secret unavailable.');
        return hash_hmac('sha256', 'registration:' . $email . ':' . $code, $secret);
    }
}
