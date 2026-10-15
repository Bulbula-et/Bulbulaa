<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Csrf;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Repositories\StaffRepository;
use Bulbula\Services\AuditService;
use Bulbula\Services\StaffAuthService;

final class ProfileController
{
    public function __construct(
        private readonly StaffAuthService $auth = new StaffAuthService(),
        private readonly StaffRepository $staffRepo = new StaffRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {}

    public function index(Request $request): Response
    {
        $staff = $this->auth->currentStaff();
        if ($staff === null) return Response::redirect('/staff/login');
        return Response::html(View::render('staff/profile', [
            'pageTitle' => 'My Profile & Security', 'activeNav' => 'profile',
            'staff' => $staff,
            'sessionMetadata' => Session::metadata(),
            'securityEvents' => $this->audit->listStaffSecurityEvents((int) $staff['id']),
            'activeSessions' => (new \Bulbula\Services\AccountSessionService())->list('STAFF', (int) $staff['id']),
            'pendingAuthenticator' => $this->auth->pendingAuthenticatorReplacement($staff),
        ], 'staff'));
    }

    public function beginAuthenticatorReplacement(Request $request): Response
    {
        $staff=$this->auth->currentStaff(); if($staff===null)return Response::redirect('/staff/login');
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/staff/profile');}
        $result=$this->auth->beginAuthenticatorReplacement($staff,(string)$request->input('current_password',''),(string)$request->input('current_totp',''));
        if(!$result['ok'])Session::flash('error',(string)$result['error']);else Session::flash('success','Add the new secret to your authenticator, then confirm its code. Your existing authenticator remains active until confirmation.');
        return Response::redirect('/staff/profile');
    }

    public function completeAuthenticatorReplacement(Request $request): Response
    {
        $staff=$this->auth->currentStaff(); if($staff===null)return Response::redirect('/staff/login');
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/staff/profile');}
        $result=$this->auth->completeAuthenticatorReplacement($staff,(string)$request->input('new_totp',''));
        if(!$result['ok']){Session::flash('error',(string)$result['error']);return Response::redirect('/staff/profile');}
        (new \Bulbula\Services\AccountSessionService())->revokeOthers('STAFF',(int)$staff['id']);
        $this->audit->logStaffAction((int)$staff['id'],'STAFF_2FA_REPLACED','staff_user',(int)$staff['id'],null,['self_service'=>true,'other_sessions_revoked'=>true]);
        Session::flash('success','Your authenticator was replaced and all other devices were signed out.');return Response::redirect('/staff/profile');
    }

    public function revokeSession(Request $request): Response
    {
        $staff=$this->auth->currentStaff();if($staff===null)return Response::redirect('/staff/login');
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/staff/profile');}
        $sessionId=(int)$request->param('id',0);
        (new \Bulbula\Services\AccountSessionService())->revoke($sessionId,'STAFF',(int)$staff['id']);
        $this->audit->logStaffAction((int)$staff['id'],'STAFF_SESSION_REVOKED','staff_user',(int)$staff['id'],null,['remote_session_revoked'=>true]);
        Session::flash('success','The selected Staff device was signed out.');return Response::redirect('/staff/profile');
    }

    public function revokeOtherSessions(Request $request): Response
    {
        $staff=$this->auth->currentStaff();if($staff===null)return Response::redirect('/staff/login');
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/staff/profile');}
        (new \Bulbula\Services\AccountSessionService())->revokeOthers('STAFF',(int)$staff['id']);
        $this->audit->logStaffAction((int)$staff['id'],'STAFF_OTHER_SESSIONS_REVOKED','staff_user',(int)$staff['id']);
        Session::flash('success','All other Staff sessions were revoked.');return Response::redirect('/staff/profile');
    }

    public function updateProfile(Request $request): Response
    {
        $staff = $this->auth->currentStaff();
        if ($staff === null) return Response::redirect('/staff/login');
        if (!Csrf::verify((string) $request->input('_csrf', ''))) { Session::flash('error', 'Security token expired.'); return Response::redirect('/staff/profile'); }
        $name = trim((string) $request->input('full_name', ''));
        $email = mb_strtolower(trim((string) $request->input('email', '')), 'UTF-8');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Enter a valid full name and email address.'); return Response::redirect('/staff/profile');
        }
        if (!$this->auth->verifyCurrentPassword($staff, (string) $request->input('current_password', ''))) {
            Session::flash('error', 'Confirm your current password to update your profile.'); return Response::redirect('/staff/profile');
        }
        $emailChanged = !hash_equals(mb_strtolower((string)$staff['email'], 'UTF-8'), $email);
        if ($emailChanged && !$this->auth->verifyCurrentAuthenticator($staff, (string)$request->input('current_totp', ''))) {
            Session::flash('error', 'A valid authenticator code is required to change your sign-in email.'); return Response::redirect('/staff/profile');
        }
        try {
            $before = ['full_name' => $staff['full_name'], 'email' => $staff['email']];
            $this->staffRepo->updateProfile((int) $staff['id'], $name, $email);
            if ($emailChanged) (new \Bulbula\Services\AccountSessionService())->revokeOthers('STAFF', (int)$staff['id']);
            $this->audit->logStaffAction((int) $staff['id'], $emailChanged ? 'STAFF_EMAIL_CHANGED' : 'STAFF_PROFILE_UPDATED', 'staff_user', (int) $staff['id'], $before, ['full_name' => $name, 'email' => $email, 'other_sessions_revoked' => $emailChanged]);
            Session::flash('success', $emailChanged ? 'Your sign-in email was changed and all other devices were signed out.' : 'Your profile was updated.');
        } catch (\Throwable) { Session::flash('error', 'That email is already in use or the profile could not be updated.'); }
        return Response::redirect('/staff/profile');
    }

    public function changePassword(Request $request): Response
    {
        $staff = $this->auth->currentStaff();
        if ($staff === null) return Response::redirect('/staff/login');
        if (!Csrf::verify((string) $request->input('_csrf', ''))) { Session::flash('error', 'Security token expired.'); return Response::redirect('/staff/profile'); }
        $new = (string) $request->input('new_password', '');
        if (!hash_equals($new, (string) $request->input('new_password_confirmation', ''))) {
            Session::flash('error', 'New password confirmation does not match.'); return Response::redirect('/staff/profile');
        }
        $result = $this->auth->changePassword($staff, (string) $request->input('current_password', ''), $new);
        if (!$result['ok']) { Session::flash('error', (string) $result['error']); return Response::redirect('/staff/profile'); }
        (new \Bulbula\Services\AccountSessionService())->revokeOthers('STAFF', (int) $staff['id']);
        $this->audit->logStaffAction((int) $staff['id'], 'STAFF_PASSWORD_CHANGED', 'staff_user', (int) $staff['id'], null, ['self_service' => true, 'other_sessions_revoked' => true]);
        Session::flash('success', 'Your password was changed and all other devices were signed out.');
        return Response::redirect('/staff/profile');
    }
}
