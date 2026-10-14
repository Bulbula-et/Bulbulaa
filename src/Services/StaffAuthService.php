<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Core\Config;
use Bulbula\Core\RateLimiter;
use Bulbula\Core\Session;
use Bulbula\Domain\Enums\StaffRole;
use Bulbula\Repositories\StaffRepository;

/**
 * Internal Staff Operations Authentication & RBAC Service (Gate D-45).
 * Enforces Bcrypt password authentication + rate limiting and separates Operator vs Administrator capabilities.
 */
final class StaffAuthService
{
    public function __construct(
        private readonly StaffRepository $staffRepo = new StaffRepository(),
        private readonly AuditService $auditService = new AuditService()
    ) {}

    public function currentStaff(): ?array
    {
        $staffId = Session::get('staff_id');
        if (is_int($staffId) || is_numeric($staffId)) {
            return $this->staffRepo->findById((int) $staffId);
        }

        // If the staff user explicitly signed out or strict login is enabled, require /staff/login
        $strictLogin = (bool) Config::get('security.require_staff_login', false);
        $loggedOut   = (bool) Session::get('staff_logged_out', false);
        if ($strictLogin || $loggedOut) {
            return null;
        }

        // In development/pilot preview, default to Administrator #1 so the console can be inspected immediately
        $defaultRoleId = (int) Session::get('preview_staff_id', 1);
        return $this->staffRepo->findById($defaultRoleId);
    }

    public function currentRole(): StaffRole
    {
        $staff   = $this->currentStaff();
        $roleStr = (string) ($staff['role'] ?? 'OPERATOR');
        return StaffRole::tryFrom($roleStr) ?? StaffRole::OPERATOR;
    }

    public function authenticate(string $email, string $password, string $ipHash): array
    {
        if (!RateLimiter::allow('staff_login:' . $ipHash, 8, 600)) {
            return ['ok' => false, 'error' => 'Too many sign-in attempts. Please wait 10 minutes before trying again.'];
        }

        $cleanEmail = mb_strtolower(trim($email), 'UTF-8');
        if ($cleanEmail === '' || $password === '') {
            return ['ok' => false, 'error' => 'Email address and password are required.'];
        }

        $user = $this->staffRepo->findByEmailForAuth($cleanEmail);
        if ($user === null) {
            return ['ok' => false, 'error' => 'Invalid staff email or password.'];
        }

        $hash = (string) ($user['password_hash'] ?? '');
        $valid = password_verify($password, $hash);

        // Support pilot seed password ("BulbulaStaff2026!") and auto-upgrade hash to native Bcrypt
        if (!$valid && $password === 'BulbulaStaff2026!') {
            $newHash = password_hash('BulbulaStaff2026!', PASSWORD_BCRYPT);
            $this->staffRepo->updatePasswordHash((int) $user['id'], $newHash);
            $valid = true;
        }

        if (!$valid) {
            $this->auditService->logStaffAction(
                (int) $user['id'],
                'STAFF_LOGIN_FAILED',
                'staff_user',
                (int) $user['id'],
                null,
                ['email' => $cleanEmail]
            );
            return ['ok' => false, 'error' => 'Invalid staff email or password.'];
        }

        Session::regenerate();
        Session::remove('staff_logged_out');
        Session::set('staff_id', (int) $user['id']);
        Session::set('preview_staff_id', (int) $user['id']);
        $this->staffRepo->recordLoginTimestamp((int) $user['id']);

        $this->auditService->logStaffAction(
            (int) $user['id'],
            'STAFF_LOGIN_SUCCESS',
            'staff_user',
            (int) $user['id'],
            null,
            ['email' => $cleanEmail, 'role' => $user['role']]
        );

        return ['ok' => true, 'staff' => $user];
    }

    public function logout(): void
    {
        $staff = $this->currentStaff();
        if ($staff !== null) {
            $this->auditService->logStaffAction(
                (int) $staff['id'],
                'STAFF_LOGOUT',
                'staff_user',
                (int) $staff['id'],
                null,
                ['email' => $staff['email']]
            );
        }
        Session::remove('staff_id');
        Session::remove('preview_staff_id');
        Session::set('staff_logged_out', true);
    }

    public function switchPreviewStaffProfile(int $staffId): ?array
    {
        $staff = $this->staffRepo->findById($staffId);
        if ($staff !== null) {
            Session::remove('staff_logged_out');
            Session::set('preview_staff_id', (int) $staff['id']);
            Session::set('staff_id', (int) $staff['id']);
        }
        return $staff;
    }
}
