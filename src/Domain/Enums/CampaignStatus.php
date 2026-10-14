<?php
declare(strict_types=1);

namespace Bulbula\Domain\Enums;

/**
 * Staff-Managed Sponsorship Campaign States & Approved Formats (C-16, C-27).
 */
enum CampaignStatus: string
{
    case DRAFT                  = 'DRAFT';
    case PENDING_ADMIN_APPROVAL = 'PENDING_ADMIN_APPROVAL';
    case APPROVED_SCHEDULED     = 'APPROVED_SCHEDULED';
    case ACTIVE                 = 'ACTIVE';
    case COMPLETED              = 'COMPLETED';
    case TERMINATED_EARLY       = 'TERMINATED_EARLY';

    public const FORMAT_SEARCH_PLACEMENT     = 'SEARCH_PLACEMENT';
    public const FORMAT_CATEGORY_SPONSORSHIP = 'CATEGORY_SPONSORSHIP';
    public const FORMAT_HOMEPAGE_PROMOTION   = 'HOMEPAGE_PROMOTION';

    /**
     * @return string[]
     */
    public static function approvedFormats(): array
    {
        return [
            self::FORMAT_SEARCH_PLACEMENT,
            self::FORMAT_CATEGORY_SPONSORSHIP,
            self::FORMAT_HOMEPAGE_PROMOTION,
        ];
    }
}
