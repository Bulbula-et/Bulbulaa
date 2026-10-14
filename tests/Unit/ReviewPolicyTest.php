<?php
declare(strict_types=1);

use Bulbula\Domain\Policies\ReviewPolicy;
use PHPUnit\Framework\TestCase;

final class ReviewPolicyTest extends TestCase
{
    public function testValidSubmissionAndUnweightedSummary(): void
    {
        $result = ReviewPolicy::validateSubmission('5', ' Helpful local review ');
        self::assertTrue($result['valid']);
        self::assertSame(5, $result['rating']);
        self::assertSame('Helpful local review', $result['sanitized_text']);
        self::assertSame(['average' => 4.0, 'count' => 3], ReviewPolicy::computeUnweightedSummary([5, 4, 3]));
    }

    public function testRejectsInvalidRatingsAndOversizedText(): void
    {
        self::assertFalse(ReviewPolicy::validateSubmission('4.5', null)['valid']);
        self::assertFalse(ReviewPolicy::validateSubmission(0, null)['valid']);
        self::assertFalse(ReviewPolicy::validateSubmission(6, str_repeat('a', 2001))['valid']);
    }

    public function testEditWindowAndOwnershipAreEnforced(): void
    {
        $now = new DateTimeImmutable('2026-10-14 12:00:00');
        $row = ['customer_id' => 7, 'status' => 'PUBLISHED', 'first_submitted_at' => '2026-10-01 12:00:00'];
        self::assertTrue(ReviewPolicy::canCustomerEdit($row, 7, $now)['can_edit']);
        self::assertFalse(ReviewPolicy::canCustomerEdit($row, 8, $now)['can_edit']);
        $row['first_submitted_at'] = '2026-08-01 12:00:00';
        self::assertFalse(ReviewPolicy::canCustomerEdit($row, 7, $now)['can_edit']);
    }
}
