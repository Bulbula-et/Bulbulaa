<?php
declare(strict_types=1);

use Bulbula\Domain\Policies\ListingLifecyclePolicy;
use PHPUnit\Framework\TestCase;

final class ListingLifecyclePolicyTest extends TestCase
{
    public function testPublishedListingCanCloseOrUnpublishWithReason(): void
    {
        foreach (['TEMPORARILY_CLOSED','PERMANENTLY_CLOSED','UNPUBLISHED'] as $target) {
            self::assertTrue(ListingLifecyclePolicy::evaluate('PUBLISHED', $target, 'Verified operational closure reason.')['allowed']);
        }
    }

    public function testClosedListingMustReturnThroughQualityControl(): void
    {
        self::assertTrue(ListingLifecyclePolicy::evaluate('TEMPORARILY_CLOSED', 'PENDING_QC', 'Business confirmed that operations resumed.')['allowed']);
        self::assertFalse(ListingLifecyclePolicy::evaluate('TEMPORARILY_CLOSED', 'PUBLISHED', 'Business reopened today with normal hours.')['allowed']);
        self::assertFalse(ListingLifecyclePolicy::evaluate('PERMANENTLY_CLOSED', 'PUBLISHED', 'Business reopened after closure verification.')['allowed']);
    }

    public function testReasonIsMandatoryAndBounded(): void
    {
        self::assertFalse(ListingLifecyclePolicy::evaluate('PUBLISHED', 'UNPUBLISHED', 'short')['allowed']);
        self::assertFalse(ListingLifecyclePolicy::evaluate('PUBLISHED', 'UNPUBLISHED', str_repeat('x', 501))['allowed']);
    }
}
