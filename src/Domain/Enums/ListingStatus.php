<?php
declare(strict_types=1);

namespace Bulbula\Domain\Enums;

/**
 * Branch & Business Lifecycle / Publication States (C-19, C-20, C-21).
 */
enum ListingStatus: string
{
    case DRAFT              = 'DRAFT';
    case PENDING_QC         = 'PENDING_QC';
    case PUBLISHED          = 'PUBLISHED';
    case TEMPORARILY_CLOSED = 'TEMPORARILY_CLOSED';
    case PERMANENTLY_CLOSED = 'PERMANENTLY_CLOSED';
    case UNPUBLISHED        = 'UNPUBLISHED';

    public function isPubliclyVisible(): bool
    {
        return $this === self::PUBLISHED || $this === self::TEMPORARILY_CLOSED;
    }

    public function label(): string
    {
        return match ($this) {
            self::DRAFT              => 'Draft',
            self::PENDING_QC         => 'Pending QC Review',
            self::PUBLISHED          => 'Published',
            self::TEMPORARILY_CLOSED => 'Temporarily Closed',
            self::PERMANENTLY_CLOSED => 'Permanently Closed',
            self::UNPUBLISHED        => 'Unpublished',
        };
    }
}
