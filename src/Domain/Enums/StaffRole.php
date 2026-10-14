<?php
declare(strict_types=1);

namespace Bulbula\Domain\Enums;

/**
 * Internal Operations Console Roles (C-19..C-29, Gate D-45).
 */
enum StaffRole: string
{
    case OPERATOR      = 'OPERATOR';
    case ADMINISTRATOR = 'ADMINISTRATOR';

    public function canManageTaxonomyAndAreas(): bool
    {
        return $this === self::ADMINISTRATOR;
    }

    public function canApproveCampaigns(): bool
    {
        return $this === self::ADMINISTRATOR;
    }

    public function canViewAuditLogs(): bool
    {
        return $this === self::ADMINISTRATOR;
    }

    public function canHandleReviewAppeals(): bool
    {
        return $this === self::ADMINISTRATOR;
    }
}
