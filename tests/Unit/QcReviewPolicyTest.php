<?php
declare(strict_types=1);

use Bulbula\Domain\Policies\QcReviewPolicy;
use PHPUnit\Framework\TestCase;

final class QcReviewPolicyTest extends TestCase
{
    private array $complete = ['permission_verified'=>true,'coordinates_checked'=>true,'category_checked'=>true];

    public function testIndependentReviewerCanPassOnlyWithCompleteChecklist(): void
    {
        self::assertTrue(QcReviewPolicy::evaluate('PASSED', $this->complete, '', 10, 11, true)['allowed']);
        $incomplete = $this->complete;
        $incomplete['coordinates_checked'] = false;
        self::assertFalse(QcReviewPolicy::evaluate('PASSED', $incomplete, '', 10, 11, true)['allowed']);
    }

    public function testSelfReviewIsBlockedWhenAnotherReviewerExists(): void
    {
        $result = QcReviewPolicy::evaluate('PASSED', $this->complete, 'Prepared and reviewed by the same person.', 10, 10, true);
        self::assertFalse($result['allowed']);
    }

    public function testControlledFallbackNeedsExplanationWhenNoReviewerExists(): void
    {
        self::assertFalse(QcReviewPolicy::evaluate('PASSED', $this->complete, '', 10, 10, false)['allowed']);
        $result = QcReviewPolicy::evaluate('PASSED', $this->complete, 'Only active reviewer; all evidence was rechecked.', 10, 10, false);
        self::assertTrue($result['allowed']);
        self::assertTrue($result['checklist']['self_review_fallback']);
    }

    public function testReturnedAndRejectedDecisionsNeedReason(): void
    {
        self::assertFalse(QcReviewPolicy::evaluate('REJECTED', [], 'short', 10, 11, true)['allowed']);
        self::assertTrue(QcReviewPolicy::evaluate('RETURNED_FOR_FIXES', [], 'Address evidence does not match the Branch.', 10, 11, true)['allowed']);
    }
}
