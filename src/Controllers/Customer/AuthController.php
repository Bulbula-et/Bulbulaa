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
        $otp = $this->authService->issueEmailOtp((string) $request->input('email', ''), $request->clientIpHash());
        if (!empty($otp['ok'])) {
            Session::set('otp_pending_email', $otp['email']);
            if (!empty($otp['dev_preview_otp'])) Session::set('otp_dev_preview', $otp['dev_preview_otp']);
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
        Session::flash('success', 'Welcome back, ' . $result['customer']['display_name'] . '.');
        return Response::redirect($returnTo);
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
