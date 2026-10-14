<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Config;
use Bulbula\Core\Csrf;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Services\StaffAuthService;

/**
 * Gate D-45 Internal Operations Console Authentication Controller (/staff/login & /staff/logout).
 */
final class StaffAuthController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService()
    ) {}

    public function showLogin(Request $request): Response
    {
        $returnTo = (string) $request->query('return_to', '/staff');
        if (!str_starts_with($returnTo, '/staff') || $returnTo === '/staff/login') {
            $returnTo = '/staff';
        }

        $html = View::render('staff/login', [
            'pageTitle'    => 'Staff Console Sign In (Gate D-45) — Bulbula.et',
            'canonicalUrl' => '/staff/login',
            'request'      => $request,
            'returnTo'     => $returnTo,
            'isDebug'      => Config::previewFeaturesEnabled(),
        ]);

        return Response::html($html);
    }

    public function login(Request $request): Response
    {
        $returnTo = (string) $request->input('return_to', '/staff');
        if (!str_starts_with($returnTo, '/staff') || $returnTo === '/staff/login') {
            $returnTo = '/staff';
        }

        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security session expired. Please try again.');
            return Response::redirect('/staff/login?return_to=' . rawurlencode($returnTo));
        }

        $email    = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');

        $res = $this->staffAuth->authenticate($email, $password, $request->clientIpHash());
        if (!$res['ok']) {
            Session::flash('error', (string) ($res['error'] ?? 'Authentication failed.'));
            return Response::redirect('/staff/login?return_to=' . rawurlencode($returnTo));
        }

        if (!empty($res['requires_2fa'])) {
            Session::set('staff_2fa_return_to', $returnTo);
            return Response::redirect('/staff/2fa');
        }

        Session::flash('success', 'Signed in as ' . ($res['staff']['full_name'] ?? 'Staff') . ' (' . ($res['staff']['role'] ?? '') . ').');
        return Response::redirect($returnTo);
    }

    public function showTwoFactor(Request $request): Response
    {
        $staff = $this->staffAuth->pendingTwoFactor();
        if ($staff === null) {
            Session::flash('error', 'Your two-factor challenge expired. Please sign in again.');
            return Response::redirect('/staff/login');
        }
        $enrollment = $this->staffAuth->enrollmentDetails();
        return Response::html(View::render('staff/two-factor', [
            'pageTitle' => 'Staff Two-Factor Verification — Bulbula.et',
            'request' => $request,
            'staffPending' => $staff,
            'enrollment' => $enrollment,
        ]));
    }

    public function verifyTwoFactor(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security session expired. Please try again.');
            return Response::redirect('/staff/2fa');
        }
        $result = $this->staffAuth->completeTwoFactor((string) $request->input('totp_code', ''), $request->clientIpHash());
        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Two-factor verification failed.'));
            return Response::redirect('/staff/2fa');
        }
        $returnTo = (string) Session::get('staff_2fa_return_to', '/staff');
        Session::remove('staff_2fa_return_to');
        if (!str_starts_with($returnTo, '/staff') || in_array($returnTo, ['/staff/login', '/staff/2fa'], true)) $returnTo = '/staff';
        Session::flash('success', 'Two-factor verification complete. Signed in securely.');
        return Response::redirect($returnTo);
    }

    public function logout(Request $request): Response
    {
        if (Csrf::verify((string) $request->input('_csrf', ''))) {
            $this->staffAuth->logout();
            Session::flash('success', 'You have been signed out of the Staff Operations Console.');
        }
        return Response::redirect('/staff/login');
    }
}
