<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Customer;

use Bulbula\Core\Csrf;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Repositories\CustomerRepository;
use Bulbula\Repositories\ReviewRepository;
use Bulbula\Services\AuditService;
use Bulbula\Services\CustomerAuthService;

/**
 * C-33 Customer Minimal Profile & C-36 Account Deletion / Privacy Rights Controller.
 */
final class ProfileController
{
    public function __construct(
        private readonly CustomerAuthService $authService = new CustomerAuthService(),
        private readonly CustomerRepository $customerRepo = new CustomerRepository(),
        private readonly ReviewRepository $reviewRepo = new ReviewRepository(),
        private readonly AuditService $auditService = new AuditService()
    ) {}

    public function index(Request $request): Response
    {
        $customer = $this->authService->currentCustomer();
        if ($customer === null) {
            return Response::redirect('/auth/login?return_to=%2Faccount');
        }

        $saved   = $this->customerRepo->listSavedBusinesses((int) $customer['id']);
        $reviews = $this->reviewRepo->listByCustomer((int) $customer['id']);

        $html = View::render('customer/profile', [
            'pageTitle'    => 'My Bulbula Identity & Controls — Bulbula.et',
            'canonicalUrl' => '/account',
            'request'      => $request,
            'customer'     => $customer,
            'savedItems'   => $saved,
            'myReviews'    => $reviews,
            'emailVerificationDevPreview' => Session::get('email_verification_dev_preview'),
            'sessionMetadata' => Session::metadata(),
            'securityEvents' => $this->auditService->listCustomerSecurityEvents((int) $customer['id']),
            'activeSessions' => (new \Bulbula\Services\AccountSessionService())->list('CUSTOMER', (int) $customer['id']),
        ]);

        return Response::html($html);
    }

    public function requestEmailVerification(Request $request): Response
    {
        $customer = $this->authService->currentCustomer();
        if ($customer === null) return Response::redirect('/auth/login');
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.'); return Response::redirect('/account');
        }
        $result = $this->authService->issueEmailVerification($customer, $request->clientIpHash());
        if (!$result['ok']) { Session::flash('error', (string) $result['error']); return Response::redirect('/account'); }
        if (!empty($result['dev_preview_otp'])) Session::set('email_verification_dev_preview', $result['dev_preview_otp']);
        Session::flash('success', !empty($result['already_verified']) ? 'Your email is already verified.' : 'A verification code was sent to your email.');
        return Response::redirect('/account');
    }

    public function verifyEmail(Request $request): Response
    {
        $customer = $this->authService->currentCustomer();
        if ($customer === null) return Response::redirect('/auth/login');
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.'); return Response::redirect('/account');
        }
        $result = $this->authService->verifyCustomerEmail($customer, (string) $request->input('otp_code', ''));
        if (!$result['ok']) { Session::flash('error', (string) $result['error']); return Response::redirect('/account'); }
        Session::forget('email_verification_dev_preview');
        $this->auditService->logCustomerAction((int) $customer['id'], 'CUSTOMER_EMAIL_VERIFIED', 'customer', (int) $customer['id'], []);
        Session::flash('success', 'Your email address is now verified.');
        return Response::redirect('/account');
    }

    public function revokeSession(Request $request): Response
    {
        $customer=$this->authService->currentCustomer(); if($customer===null)return Response::redirect('/auth/login');
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/account');}
        (new \Bulbula\Services\AccountSessionService())->revoke((int)$request->param('id',0),'CUSTOMER',(int)$customer['id']);
        Session::flash('success','The selected device was signed out.'); return Response::redirect('/account');
    }

    public function revokeOtherSessions(Request $request): Response
    {
        $customer=$this->authService->currentCustomer(); if($customer===null)return Response::redirect('/auth/login');
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/account');}
        (new \Bulbula\Services\AccountSessionService())->revokeOthers('CUSTOMER',(int)$customer['id']);
        Session::flash('success','All other devices were signed out.'); return Response::redirect('/account');
    }

    public function updateProfile(Request $request): Response
    {
        $customer = $this->authService->currentCustomer();
        if ($customer === null) return Response::redirect('/auth/login');
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.'); return Response::redirect('/account');
        }
        $name = trim((string) $request->input('display_name', ''));
        $phone = trim((string) $request->input('phone_number', ''));
        $language = (string) $request->input('preferred_language', 'en');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || ($phone !== '' && !preg_match('/^\+?[0-9 ()-]{7,32}$/', $phone)) || !in_array($language, ['en', 'am'], true)) {
            Session::flash('error', 'Please provide a valid name, phone number, and language.'); return Response::redirect('/account');
        }
        $this->customerRepo->updateProfile((int) $customer['id'], $name, $phone !== '' ? $phone : null, $language);
        $this->auditService->logCustomerAction((int) $customer['id'], 'CUSTOMER_PROFILE_UPDATED', 'customer', (int) $customer['id'], ['preferred_language' => $language]);
        Session::flash('success', 'Your personal profile has been updated.');
        return Response::redirect('/account');
    }

    public function changePassword(Request $request): Response
    {
        $customer = $this->authService->currentCustomer();
        if ($customer === null) return Response::redirect('/auth/login');
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.'); return Response::redirect('/account');
        }
        $new = (string) $request->input('new_password', '');
        if (!hash_equals($new, (string) $request->input('new_password_confirmation', ''))) {
            Session::flash('error', 'New password confirmation does not match.'); return Response::redirect('/account');
        }
        $result = $this->authService->changePassword($customer, (string) $request->input('current_password', ''), $new);
        if (!$result['ok']) { Session::flash('error', (string) $result['error']); return Response::redirect('/account'); }
        $this->auditService->logCustomerAction((int) $customer['id'], 'CUSTOMER_PASSWORD_CHANGED', 'customer', (int) $customer['id'], []);
        Session::flash('success', 'Your password has been changed securely.');
        return Response::redirect('/account');
    }

    public function requestDeletion(Request $request): Response
    {
        $customer = $this->authService->currentCustomer();
        if ($customer === null) {
            return Response::redirect('/auth/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/account');
        }

        $requestType = (string) $request->input('request_type', 'ACCOUNT_DELETION');
        $notes       = trim((string) $request->input('notes', ''));

        $reqId = $this->customerRepo->createPrivacyRequest(
            (int) $customer['id'],
            (string) $customer['email'],
            in_array($requestType, ['ACCOUNT_DELETION', 'DATA_ACCESS'], true) ? $requestType : 'ACCOUNT_DELETION',
            $notes
        );

        $this->auditService->logCustomerAction(
            (int) $customer['id'],
            'PRIVACY_REQUEST_SUBMITTED',
            'privacy_request',
            $reqId,
            ['request_type' => $requestType]
        );

        Session::flash('success', 'Your privacy / account deletion request (#' . $reqId . ') has been recorded and routed for audited processing.');
        return Response::redirect('/account');
    }
}
