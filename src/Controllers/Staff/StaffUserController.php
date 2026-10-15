<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Csrf;
use Bulbula\Core\Database;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Repositories\StaffRepository;
use Bulbula\Repositories\AccountSessionRepository;
use Bulbula\Services\AuditService;
use Bulbula\Services\StaffAuthService;

/**
 * Gate D-45 Administrator Staff Account & RBAC Manager Controller (/staff/users).
 */
final class StaffUserController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly StaffRepository $staffRepo = new StaffRepository(),
        private readonly AuditService $auditService = new AuditService()
    ) {}

    public function index(Request $request): Response
    {
        $role = $this->staffAuth->currentRole();
        $html = View::render('staff/users', [
            'pageTitle'  => 'Staff Accounts & Access Management — Staff Console',
            'activeNav'  => 'users',
            'staff'      => $this->staffAuth->currentStaff(),
            'canManage'  => $role->canApproveCampaigns(),
            'staffUsers' => $this->staffRepo->listAllStaff(),
        ], 'staff');

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/users');
        }

        if (!$this->staffAuth->currentRole()->canApproveCampaigns()) {
            Session::flash('error', 'Only an Administrator can create staff accounts.');
            return Response::redirect('/staff/users');
        }

        $fullName = trim((string) $request->input('full_name', ''));
        $email    = mb_strtolower(trim((string) $request->input('email', '')), 'UTF-8');
        $role     = (string) $request->input('role', 'OPERATOR');
        $password = (string) $request->input('password', '');

        if ($fullName === '' || $email === '' || strlen($password) < 8) {
            Session::flash('error', 'Full name, email, and a password of at least 8 characters are required.');
            return Response::redirect('/staff/users');
        }

        try {
            $hash  = password_hash($password, PASSWORD_BCRYPT);
            $newId = $this->staffRepo->createStaff($email, $fullName, $role, $hash);

            $actor = $this->staffAuth->currentStaff();
            $this->auditService->logStaffAction(
                (int) ($actor['id'] ?? 1),
                'STAFF_USER_CREATED',
                'staff_user',
                $newId,
                null,
                ['email' => $email, 'full_name' => $fullName, 'role' => $role]
            );

            Session::flash('success', 'Staff account "' . $fullName . '" (' . $role . ') created.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Could not create staff user: ' . $e->getMessage());
        }

        return Response::redirect('/staff/users');
    }

    public function toggleActive(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/users');
        }

        if (!$this->staffAuth->currentRole()->canApproveCampaigns()) {
            Session::flash('error', 'Only an Administrator can modify staff accounts.');
            return Response::redirect('/staff/users');
        }

        $targetId = (int) $request->param('id', 0);
        $actor    = $this->staffAuth->currentStaff();
        if ($targetId === (int) ($actor['id'] ?? 0)) {
            Session::flash('error', 'You cannot deactivate your own active session account.');
            return Response::redirect('/staff/users');
        }

        $target = $this->staffRepo->findByIdAnyStatus($targetId);
        if ($target !== null) {
            $newState = empty($target['is_active']);
            $this->staffRepo->setActiveStatus($targetId, $newState);
            $this->auditService->logStaffAction(
                (int) ($actor['id'] ?? 1),
                'STAFF_USER_STATUS_CHANGED',
                'staff_user',
                $targetId,
                ['is_active' => $target['is_active']],
                ['is_active' => $newState ? 1 : 0]
            );
            Session::flash('success', 'Staff account #' . $targetId . ' ' . ($newState ? 'activated' : 'suspended') . '.');
        }

        return Response::redirect('/staff/users');
    }

    public function resetTotp(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/users');
        }
        if (!$this->staffAuth->currentRole()->canApproveCampaigns()) {
            Session::flash('error', 'Only an Administrator can reset staff two-factor enrollment.');
            return Response::redirect('/staff/users');
        }
        $targetId = (int) $request->param('id', 0);
        $actor = $this->staffAuth->currentStaff();
        if ($targetId <= 0 || $targetId === (int) ($actor['id'] ?? 0)) {
            Session::flash('error', 'You cannot reset your own active two-factor enrollment. Another Administrator must do this.');
            return Response::redirect('/staff/users');
        }
        Database::transaction(function () use ($targetId): void {
            $this->staffRepo->disableTotp($targetId);
            (new AccountSessionRepository())->revokeAll('STAFF', $targetId);
        });
        $this->auditService->logStaffAction((int) ($actor['id'] ?? 1), 'STAFF_2FA_RESET', 'staff_user', $targetId, null, ['totp_enabled' => false]);
        Session::flash('success', 'Two-factor enrollment reset for Staff #' . $targetId . '. They must enroll again at next login.');
        return Response::redirect('/staff/users');
    }

    public function resetPassword(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/users');
        }

        if (!$this->staffAuth->currentRole()->canApproveCampaigns()) {
            Session::flash('error', 'Only an Administrator can reset staff passwords.');
            return Response::redirect('/staff/users');
        }

        $targetId    = (int) $request->param('id', 0);
        $newPassword = (string) $request->input('new_password', '');
        if ($targetId <= 0 || strlen($newPassword) < 8) {
            Session::flash('error', 'New password must be at least 8 characters.');
            return Response::redirect('/staff/users');
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->staffRepo->updatePasswordHash($targetId, $hash);

        $actor = $this->staffAuth->currentStaff();
        $this->auditService->logStaffAction(
            (int) ($actor['id'] ?? 1),
            'STAFF_PASSWORD_RESET',
            'staff_user',
            $targetId,
            null,
            ['password_reset' => true]
        );

        Session::flash('success', 'Password updated for Staff #' . $targetId . '.');
        return Response::redirect('/staff/users');
    }
}
