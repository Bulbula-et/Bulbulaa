<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Customer;

use Bulbula\Core\Config;
use Bulbula\Core\Csrf;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Services\CustomerAuthService;
use Bulbula\Services\GoogleIdentityService;

/**
 * C-30, C-31, C-32 Password-Free Customer Authentication Controller (Google Auth + Email OTP).
 */
final class AuthController
{
    public function __construct(
        private readonly CustomerAuthService $authService = new CustomerAuthService(),
        private readonly GoogleIdentityService $googleIdentity = new GoogleIdentityService()
    ) {}

    public function showLogin(Request $request): Response
    {
        $customer = $this->authService->currentCustomer();
        if ($customer !== null) {
            return Response::redirect('/account');
        }

        $returnTo     = (string) $request->query('return_to', '/account');
        $pendingEmail = Session::get('otp_pending_email');
        $devOtp       = Session::get('otp_dev_preview');

        $html = View::render('customer/login', [
            'pageTitle'    => 'Sign In to Bulbula.et',
            'canonicalUrl' => '/auth/login',
            'request'      => $request,
            'customer'     => null,
            'returnTo'     => $returnTo,
            'pendingEmail'  => $pendingEmail,
            'devOtp'        => $devOtp,
            'googleClientId' => (string) Config::get('auth.google_client_id', ''),
        ]);

        return Response::html($html);
    }

    public function showRegister(Request $request): Response
    {
        if ($this->authService->currentCustomer() !== null) return Response::redirect('/account');
        return Response::html(View::render('customer/register', [
            'pageTitle' => 'Create Your Bulbula.et Account', 'canonicalUrl' => '/auth/register',
            'request' => $request, 'customer' => null,
        ]));
    }

    public function register(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security session expired. Please try again.');
            return Response::redirect('/auth/register');
        }
        $password = (string) $request->input('password', '');
        if (!hash_equals($password, (string) $request->input('password_confirmation', ''))) {
            Session::flash('error', 'Password confirmation does not match.');
            return Response::redirect('/auth/register');
        }
        $result = $this->authService->registerWithPassword(
            (string) $request->input('email', ''), (string) $request->input('display_name', ''),
            $password, $request->surface()
        );
        if (!$result['ok']) {
            Session::flash('error', (string) $result['error']);
            return Response::redirect('/auth/register');
        }
        $otp = $this->authService->issueEmailVerification($result['customer'], $request->clientIpHash());
        if (!empty($otp['ok']) && !empty($otp['dev_preview_otp'])) {
            Session::set('email_verification_dev_preview', $otp['dev_preview_otp']);
        }
        Session::flash('success', 'Your account was created. Verify your email to complete setup.');
        return Response::redirect('/account');
    }

    public function passwordLogin(Request $request): Response
    {
        $returnTo = (string) $request->input('return_to', '/account');
        if (!str_starts_with($returnTo, '/')) $returnTo = '/account';
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security session expired. Please try again.');
            return Response::redirect('/auth/login');
        }
        $result = $this->authService->signInWithPassword(
            (string) $request->input('email', ''), (string) $request->input('password', ''), $request->clientIpHash()
        );
        if (!$result['ok']) {
            Session::flash('error', (string) $result['error']);
            return Response::redirect('/auth/login?return_to=' . rawurlencode($returnTo));
        }
        if (!empty($result['restore_required'])) return Response::redirect('/auth/restore-account');
        Session::flash('success', 'Welcome back, ' . $result['customer']['display_name'] . '.');
        return Response::redirect($returnTo);
    }

    public function showRestoreAccount(Request $request): Response
    {
        $customer=$this->authService->pendingRestoreCustomer();if($customer===null)return Response::redirect('/auth/login');
        return Response::html(View::render('customer/restore-account',['pageTitle'=>'Restore Your Account — Bulbula.et','canonicalUrl'=>'/auth/restore-account','request'=>$request,'customer'=>null,'pendingCustomer'=>$customer]));
    }

    public function restoreAccount(Request $request): Response
    {
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security session expired.');return Response::redirect('/auth/restore-account');}
        $result=$this->authService->restorePendingAccount();if(!$result['ok']){Session::flash('error',(string)$result['error']);return Response::redirect('/auth/login');}
        Session::flash('success','Your account and access have been restored.');return Response::redirect('/account');
    }

    public function permanentlyDeleteAccount(Request $request): Response
    {
        if(!Csrf::verify((string)$request->input('_csrf',''))||trim((string)$request->input('confirmation',''))!=='DELETE'){Session::flash('error','Type DELETE exactly to permanently erase the account.');return Response::redirect('/auth/restore-account');}
        $result=$this->authService->permanentlyDeletePendingAccount();if(!$result['ok'])Session::flash('error',(string)$result['error']);else Session::flash('success','Your account has been permanently deleted.');return Response::redirect('/');
    }

    public function showForgotPassword(Request $request): Response
    {
        if ((string) $request->query('restart', '') === '1') {
            Session::forget('password_reset_email');
            Session::forget('password_reset_dev_preview');
            Session::forget('password_reset_customer_id');
            Session::forget('password_reset_verified_at');
        }
        return Response::html(View::render('customer/forgot-password', [
            'pageTitle' => 'Recover Your Password — Bulbula.et', 'canonicalUrl' => '/auth/forgot-password',
            'request' => $request, 'customer' => null,
            'pendingEmail' => Session::get('password_reset_email'), 'devOtp' => Session::get('password_reset_dev_preview'),
            'resetVerified' => (int) Session::get('password_reset_customer_id', 0) > 0,
        ]));
    }

    public function requestPasswordReset(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security session expired.'); return Response::redirect('/auth/forgot-password');
        }
        $email = mb_strtolower(trim((string) $request->input('email', '')), 'UTF-8');
        $existing = $this->authService->issuePasswordResetOtp($email, $request->clientIpHash());
        if (!empty($existing['ok'])) {
            Session::set('password_reset_email', $email);
            if (!empty($existing['dev_preview_otp'])) Session::set('password_reset_dev_preview', $existing['dev_preview_otp']);
        }
        Session::flash('success', 'If an active account matches that email, a password recovery code has been sent.');
        return Response::redirect('/auth/forgot-password');
    }

    public function verifyPasswordReset(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security session expired.'); return Response::redirect('/auth/forgot-password');
        }
        $result = $this->authService->verifyPasswordResetOtp((string) Session::get('password_reset_email', ''), (string) $request->input('otp_code', ''));
        if (!$result['ok']) { Session::flash('error', (string) $result['error']); return Response::redirect('/auth/forgot-password'); }
        Session::forget('password_reset_dev_preview');
        return Response::redirect('/auth/reset-password');
    }

    public function showResetPassword(Request $request): Response
    {
        if ((int) Session::get('password_reset_customer_id', 0) <= 0) return Response::redirect('/auth/forgot-password');
        return Response::html(View::render('customer/reset-password', [
            'pageTitle' => 'Choose a New Password — Bulbula.et', 'canonicalUrl' => '/auth/reset-password',
            'request' => $request, 'customer' => null,
        ]));
    }

    public function resetPassword(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security session expired.'); return Response::redirect('/auth/reset-password');
        }
        $password = (string) $request->input('password', '');
        if (!hash_equals($password, (string) $request->input('password_confirmation', ''))) {
            Session::flash('error', 'Password confirmation does not match.'); return Response::redirect('/auth/reset-password');
        }
        $result = $this->authService->completePasswordReset($password);
        if (!$result['ok']) { Session::flash('error', (string) $result['error']); return Response::redirect('/auth/reset-password'); }
        Session::forget('password_reset_email');
        Session::flash('success', 'Your password has been reset and you are signed in.');
        return Response::redirect('/account');
    }

    public function requestEmailOtp(Request $request): Response
    {
        $returnTo = (string) $request->input('return_to', '/account');
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security session expired. Please try again.');
            return Response::redirect('/auth/login');
        }

        $email  = (string) $request->input('email', '');
        $result = $this->authService->issueEmailOtp($email, $request->clientIpHash());

        if (!$result['ok']) {
            Session::flash('error', (string) $result['error']);
            return Response::redirect('/auth/login?return_to=' . rawurlencode($returnTo));
        }

        Session::set('otp_pending_email', $result['email']);
        if (!empty($result['dev_preview_otp'])) {
            Session::set('otp_dev_preview', $result['dev_preview_otp']);
        }

        Session::flash('success', 'A 6-digit one-time verification code has been issued for ' . $result['email'] . '.');
        return Response::redirect('/auth/login?return_to=' . rawurlencode($returnTo));
    }

    public function verifyEmailOtp(Request $request): Response
    {
        $returnTo = (string) $request->input('return_to', '/account');
        if (!str_starts_with($returnTo, '/')) {
            $returnTo = '/account';
        }

        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security session expired. Please try again.');
            return Response::redirect('/auth/login');
        }

        $email  = (string) $request->input('email', (string) Session::get('otp_pending_email', ''));
        $code   = (string) $request->input('otp_code', '');
        $result = $this->authService->verifyEmailOtp($email, $code, $request->surface());

        if (!$result['ok']) {
            Session::flash('error', (string) $result['error']);
            return Response::redirect('/auth/login?return_to=' . rawurlencode($returnTo));
        }

        Session::forget('otp_pending_email');
        Session::forget('otp_dev_preview');
        if (!empty($result['restore_required'])) return Response::redirect('/auth/restore-account');
        Session::flash('success', 'Signed in as ' . $result['customer']['display_name'] . '.');
        return Response::redirect($returnTo);
    }

    public function googleCallback(Request $request): Response
    {
        $returnTo = (string) $request->input('return_to', '/account');
        if (!str_starts_with($returnTo, '/')) {
            $returnTo = '/account';
        }

        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/auth/login');
        }

        $credential = (string) $request->input('credential', '');
        $verified   = $this->googleIdentity->verifyIdToken($credential);
        if (!$verified['ok']) {
            Session::flash('error', (string) ($verified['error'] ?? 'Google Sign-In failed.'));
            return Response::redirect('/auth/login?return_to=' . rawurlencode($returnTo));
        }

        $identity = $verified['identity'];
        $customer = $this->authService->signInWithVerifiedGoogleIdentity(
            (string) $identity['email'],
            (string) $identity['name'],
            (string) $identity['sub'],
            $request->surface()
        );
        if (!empty($customer['_restore_required'])) return Response::redirect('/auth/restore-account');
        if (($customer['account_status'] ?? '') === 'DELETED') { Session::flash('error','This account is no longer available.'); return Response::redirect('/auth/login'); }
        Session::flash('success', 'Signed in with Google as ' . $customer['display_name'] . '.');
        return Response::redirect($returnTo);
    }

    public function logout(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security session expired. Please try again.');
            return Response::redirect('/account');
        }
        $this->authService->logout();
        Session::flash('success', 'You have been signed out. You can continue browsing Bulbula.et as a guest.');
        return Response::redirect('/');
    }
}
