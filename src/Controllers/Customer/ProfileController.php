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
        if ((int) Session::get('sensitive_action_requested_at', 0) < time() - 600) {
            foreach (['sensitive_action_type','sensitive_action_requested_at','sensitive_action_confirmed_at','sensitive_action_dev_preview'] as $key) Session::forget($key);
        }
        if ((int) Session::get('email_change_requested_at', 0) < time() - 600) {
            foreach (['email_change_new_email','email_change_stage','email_change_requested_at','email_change_dev_preview'] as $key) Session::forget($key);
        }

        $html = View::render('customer/profile', [
            'pageTitle'    => 'My Bulbula Identity & Controls — Bulbula.et',
            'canonicalUrl' => '/account',
            'request'      => $request,
            'customer'     => $customer,
            'savedItems'   => $saved,
            'myReviews'    => $reviews,
            'emailVerificationDevPreview' => Session::get('email_verification_dev_preview'),
            'sessionMetadata' => Session::metadata(),
            'telegramLinked' => (new \Bulbula\Repositories\CustomerTelegramLinkRepository())->isLinked((int)$customer['id']),
            'securityEvents' => $this->auditService->listCustomerSecurityEvents((int) $customer['id']),
            'activeSessions' => (new \Bulbula\Services\AccountSessionService())->list('CUSTOMER', (int) $customer['id']),
            'pendingSensitiveAction' => Session::get('sensitive_action_type'),
            'sensitiveActionDevPreview' => Session::get('sensitive_action_dev_preview'),
            'emailChangeStage' => Session::get('email_change_stage'),
            'emailChangeNewEmail' => Session::get('email_change_new_email'),
            'emailChangeDevPreview' => Session::get('email_change_dev_preview'),
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
        (new \Bulbula\Services\AccountSessionService())->revokeOthers('CUSTOMER', (int) $customer['id']);
        (new \Bulbula\Services\CustomerSecurityMailer())->passwordChanged((string)$customer['email']);
        $this->auditService->logCustomerAction((int) $customer['id'], 'CUSTOMER_PASSWORD_CHANGED', 'customer', (int) $customer['id'], ['other_sessions_revoked' => true]);
        Session::flash('success', 'Your password was changed and all other devices were signed out.');
        return Response::redirect('/account');
    }

    public function cancelSecurityFlow(Request $request): Response
    {
        $customer=$this->authService->currentCustomer();if($customer===null)return Response::redirect('/auth/login');
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/account');}
        foreach(['sensitive_action_type','sensitive_action_requested_at','sensitive_action_confirmed_at','sensitive_action_dev_preview','email_change_new_email','email_change_stage','email_change_requested_at','email_change_dev_preview'] as $key)Session::forget($key);
        Session::flash('success','The pending account-security action was cancelled. No account change was made.');return Response::redirect('/account');
    }

    public function beginEmailChange(Request $request): Response
    {
        $customer=$this->authService->currentCustomer();if($customer===null)return Response::redirect('/auth/login');
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/account');}
        if(Session::get('sensitive_action_type')!==null){Session::flash('error','Cancel the current security action before changing your email.');return Response::redirect('/account');}
        $result=$this->authService->beginEmailChange($customer,(string)$request->input('new_email',''),(string)$request->input('current_password',''),$request->clientIpHash());
        if(!$result['ok'])Session::flash('error',(string)$result['error']);else {if(!empty($result['dev_preview_otp']))Session::set('email_change_dev_preview',$result['dev_preview_otp']);Session::flash('success','Confirm the code sent to your current email.');}
        return Response::redirect('/account');
    }

    public function verifyCurrentEmailChange(Request $request): Response
    {
        $customer=$this->authService->currentCustomer();if($customer===null)return Response::redirect('/auth/login');
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/account');}
        $result=$this->authService->verifyCurrentEmailForChange($customer,(string)$request->input('otp_code',''),$request->clientIpHash());
        if(!$result['ok'])Session::flash('error',(string)$result['error']);else {if(!empty($result['dev_preview_otp']))Session::set('email_change_dev_preview',$result['dev_preview_otp']);Session::flash('success','Now confirm the code sent to your new email.');}
        return Response::redirect('/account');
    }

    public function completeEmailChange(Request $request): Response
    {
        $customer=$this->authService->currentCustomer();if($customer===null)return Response::redirect('/auth/login');
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/account');}
        $old=(string)$customer['email'];$result=$this->authService->completeEmailChange($customer,(string)$request->input('otp_code',''));
        if(!$result['ok']){Session::flash('error',(string)$result['error']);return Response::redirect('/account');}
        Session::forget('email_change_dev_preview');(new \Bulbula\Services\AccountSessionService())->revokeOthers('CUSTOMER',(int)$customer['id']);
        (new \Bulbula\Services\CustomerSecurityMailer())->emailChanged($old,(string)$result['email']);
        $this->auditService->logCustomerAction((int)$customer['id'],'CUSTOMER_EMAIL_CHANGED','customer',(int)$customer['id'],['old_email'=>$old,'new_email'=>$result['email']]);
        Session::flash('success','Your sign-in email was changed and other devices were signed out.');return Response::redirect('/account');
    }

    public function beginPrivacyConfirmation(Request $request): Response
    {
        $customer=$this->authService->currentCustomer(); if($customer===null)return Response::redirect('/auth/login');
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/account');}
        if(Session::get('email_change_stage')!==null){Session::flash('error','Cancel the email change before starting another security action.');return Response::redirect('/account');}
        $type=(string)$request->input('request_type','DATA_ACCESS');
        $result=$this->authService->issueSensitiveActionOtp($customer,$request->clientIpHash(),$type);
        if(!$result['ok'])Session::flash('error',(string)$result['error']);else { if(!empty($result['dev_preview_otp']))Session::set('sensitive_action_dev_preview',$result['dev_preview_otp']); Session::flash('success','A confirmation code was sent to your email.'); }
        return Response::redirect('/account');
    }

    public function verifyPrivacyConfirmation(Request $request): Response
    {
        $customer=$this->authService->currentCustomer(); if($customer===null)return Response::redirect('/auth/login');
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/account');}
        $result=$this->authService->verifySensitiveActionOtp($customer,(string)$request->input('otp_code',''));
        if(!$result['ok']){Session::flash('error',(string)$result['error']);return Response::redirect('/account');}
        if(($result['action']??'')==='DATA_ACCESS'){
            $json=(new \Bulbula\Services\CustomerDataExportService())->build($customer);
            Session::forget('sensitive_action_type');Session::forget('sensitive_action_requested_at');Session::forget('sensitive_action_confirmed_at');Session::forget('sensitive_action_dev_preview');
            $this->auditService->logCustomerAction((int)$customer['id'],'CUSTOMER_DATA_EXPORTED','customer',(int)$customer['id'],['format'=>'JSON']);
            return Response::download($json,'bulbula-account-data-'.date('Y-m-d').'.json','application/json; charset=UTF-8');
        }
        return $this->requestDeletion($request);
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

        $confirmedAt = (int) Session::get('sensitive_action_confirmed_at', 0);
        $requestType = (string) Session::get('sensitive_action_type', '');
        if ($confirmedAt < time() - 300 || !in_array($requestType, ['ACCOUNT_DELETION', 'DATA_ACCESS'], true)) {
            Session::flash('error', 'Email confirmation is required for this account action.');
            return Response::redirect('/account');
        }
        $notes = trim((string) $request->input('notes', ''));
        $reasonCode = (string) $request->input('reason_code', '');
        if ($requestType === 'ACCOUNT_DELETION') {
            $confirmation = trim((string) $request->input('delete_confirmation', ''));
            $allowedReasons = ['PRIVACY_CONCERNS','NOT_USEFUL','TOO_MANY_EMAILS','FOUND_ALTERNATIVE','DIFFICULT_TO_USE','OTHER'];
            if ($confirmation !== 'DELETE' || !in_array($reasonCode, $allowedReasons, true)) {
                Session::flash('error', 'Select a reason and type DELETE exactly to schedule account deletion.');
                return Response::redirect('/account');
            }
        }

        $reqId = $this->customerRepo->createPrivacyRequest(
            (int) $customer['id'],
            (string) $customer['email'],
            in_array($requestType, ['ACCOUNT_DELETION', 'DATA_ACCESS'], true) ? $requestType : 'ACCOUNT_DELETION',
            mb_substr($notes, 0, 1000),
            $reasonCode !== '' ? $reasonCode : null
        );

        $this->auditService->logCustomerAction(
            (int) $customer['id'],
            'CUSTOMER_ACCOUNT_DELETION_SCHEDULED',
            'privacy_request',
            $reqId,
            ['request_type' => $requestType]
        );

        Session::forget('sensitive_action_type'); Session::forget('sensitive_action_requested_at'); Session::forget('sensitive_action_confirmed_at'); Session::forget('sensitive_action_dev_preview');
        if ($requestType === 'ACCOUNT_DELETION') {
            (new \Bulbula\Services\CustomerLifecycleMailer())->deletionScheduled((string)$customer['email'], date('Y-m-d H:i:s', time() + 30 * 86400));
            (new \Bulbula\Services\AccountSessionService())->revokeOthers('CUSTOMER', (int)$customer['id']);
            $this->authService->logout();
            Session::flash('success', 'Your account is scheduled for deletion in 30 days. Sign in during the safety period to restore it.');
            return Response::redirect('/auth/login');
        }
        Session::flash('success', 'Your data-access request (#' . $reqId . ') has been recorded.');
        return Response::redirect('/account');
    }
}
