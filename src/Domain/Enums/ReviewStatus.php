<?php
declare(strict_types=1);

namespace Bulbula\Domain\Enums;

/**
 * Branch Review Moderation Lifecycle States (C-13, C-25, C-35).
 * Rule: New and edited reviews always enter PENDING_MODERATION before public display.
 */
enum ReviewStatus: string
{
    case PENDING_MODERATION  = 'PENDING_MODERATION';
    case PUBLISHED           = 'PUBLISHED';
    case REJECTED            = 'REJECTED';
    case WITHDRAWN_BY_AUTHOR = 'WITHDRAWN_BY_AUTHOR';
    case REMOVED_BY_STAFF    = 'REMOVED_BY_STAFF';

    public function countsTowardBranchRating(): bool
    {
        return $this === self::PUBLISHED;
    }

    public function occupiesActiveCustomerSlot(): bool
    {
        return in_array($this, [
            self::PENDING_MODERATION,
            self::PUBLISHED,
            self::REJECTED,
        ], true);
    }
}
