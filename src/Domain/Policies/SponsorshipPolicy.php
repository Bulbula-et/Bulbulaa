<?php
declare(strict_types=1);

namespace Bulbula\Domain\Policies;

use Bulbula\Domain\Enums\CampaignStatus;
use Bulbula\Domain\Enums\StaffRole;
use DateTimeImmutable;

/**
 * Commercial Integrity & Sponsorship Policy (C-16, C-27, Section 07, Gate D-39).
 *
 * V1 Rules:
 * - Only 3 approved formats: SEARCH_PLACEMENT, CATEGORY_SPONSORSHIP, HOMEPAGE_PROMOTION.
 * - Operators configure; only ADMINISTRATOR role can approve/activate.
 * - First paid campaign is blocked until Gate D-39 is enabled.
 * - Target Branch must be PUBLISHED.
 * - Payment never alters verification (C-12), reviews (C-13), or organic ranking (C-02).
 */
final class SponsorshipPolicy
{
    /**
     * Evaluates whether a Campaign can be approved and activated.
     *
     * @return array{allowed: bool, violations: string[]}
     */
    public static function canApproveCampaign(
        array $campaign,
        array $targetBranch,
        StaffRole $approverRole,
        bool $gateD39Enabled
    ): array {
        $violations = [];

        if (!$approverRole->canApproveCampaigns()) {
            $violations[] = 'Only an Administrator can approve or activate a Sponsorship Campaign (C-27).';
        }

        if (!$gateD39Enabled) {
            $violations[] = 'Readiness Gate D-39: Paid campaign activation is currently blocked pending commercial-integrity, billing (D-11), and legal/tax sign-off.';
        }

        $format = (string) ($campaign['placement_format'] ?? '');
        if (!in_array($format, CampaignStatus::approvedFormats(), true)) {
            $violations[] = 'Invalid placement format. Only SEARCH_PLACEMENT, CATEGORY_SPONSORSHIP, and HOMEPAGE_PROMOTION are permitted in V1.';
        }

        if (in_array($format, [CampaignStatus::FORMAT_SEARCH_PLACEMENT, CampaignStatus::FORMAT_CATEGORY_SPONSORSHIP], true)
            && empty($campaign['target_category_id'])
        ) {
            $violations[] = sprintf('Format %s requires a controlled target Category or Subcategory (C-16).', $format);
        }

        if (($targetBranch['publication_status'] ?? '') !== 'PUBLISHED') {
            $violations[] = 'Target Branch must be in PUBLISHED status before a Sponsorship Campaign can run.';
        }

        $startsAt = new DateTimeImmutable((string) ($campaign['starts_at'] ?? 'now'));
        $endsAt   = new DateTimeImmutable((string) ($campaign['ends_at'] ?? 'now'));

        if ($endsAt <= $startsAt) {
            $violations[] = 'Campaign end date must be strictly after its start date (time-bounded rule C-27).';
        }

        return [
            'allowed'    => count($violations) === 0,
            'violations' => $violations,
        ];
    }
}
