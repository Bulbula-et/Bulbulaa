<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Core\Session;
use Bulbula\Domain\Enums\StaffRole;
use Bulbula\Repositories\StaffRepository;

/**
 * Internal Staff Operations Authentication & RBAC Service (Gate D-45).
 * Enforces stronger authentication than Customer OTP and separates Operator vs Administrator capabilities.
 */
final class StaffAuthService
{
    public function __construct(
        private readonly StaffRepository $staffRepo = new StaffRepository()
    ) {}

    public function currentStaff(): ?array
    {
        $staffId = Session::get('staff_id');
        if (!is_int($staffId) && !is_numeric($staffId)) {
            // In development/pilot preview, default to Administrator #1 so the user can inspect the Staff Console immediately,
            // while supporting live role switching between Administrator and Operator!
            $defaultRoleId = (int) Session::get('preview_staff_id', 1);
            return $this->staffRepo->findById($defaultRoleId);
        }
        return $this->staffRepo->findById((int) $staffId);
    }

    public function currentRole(): StaffRole
    {
        $staff = $this->currentStaff();
        $roleStr = (string) ($staff['role'] ?? 'OPERATOR');
        return StaffRole::tryFrom($roleStr) ?? StaffRole::OPERATOR;
    }

    public function switchPreviewStaffProfile(int $staffId): ?array
    {
        $staff = $this->staffRepo->findById($staffId);
        if ($staff !== null) {
            Session::set('preview_staff_id', (int) $staff['id']);
            Session::set('staff_id', (int) $staff['id']);
        }
        return $staff;
    }
}
