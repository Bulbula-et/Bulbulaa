<?php
declare(strict_types=1);

namespace Bulbula\Domain\Policies;

/**
 * Hard Publication Gate Policy (C-19, C-21, Page 3 & Page 7).
 *
 * V1 Product Rule:
 * "No business listing may be published without a linked Permission record
 *  and the required quality-control step."
 */
final class PublicationGate
{
    /**
     * Evaluates whether a Branch and its parent Business satisfy all V1 rules for publication.
     *
     * @param array      $business       Row from `businesses`
     * @param array      $branch         Row from `branches`
     * @param array|null $permission     Linked row from `business_permissions`
     * @param array|null $latestQcReview Latest row from `verification_reviews`
     * @return array{allowed: bool, violations: string[]}
     */
    public static function evaluate(
        array $business,
        array $branch,
        ?array $permission,
        ?array $latestQcReview
    ): array {
        $violations = [];

        // 1. Business-level completeness checks (Page 3)
        if (trim((string) ($business['name_en'] ?? '')) === '') {
            $violations[] = 'Business English name (name_en) is required.';
        }
        if (empty($business['primary_category_id'])) {
            $violations[] = 'Every published listing must have exactly one Primary Category (C-04).';
        }

        // 2. Branch-level completeness checks (Page 3 & Page 5)
        if (trim((string) ($branch['branch_name_en'] ?? '')) === '') {
            $violations[] = 'Branch name (branch_name_en) is required.';
        }
        if (empty($branch['area_id']) || trim((string) ($branch['sub_city_en'] ?? '')) === '') {
            $violations[] = 'Branch Area and Sub-city must be assigned (C-05).';
        }
        if (trim((string) ($branch['address_line_en'] ?? '')) === '') {
            $violations[] = 'Branch physical address description is required.';
        }

        // 3. Hard Permission Gate (C-19)
        if ($permission === null) {
            $violations[] = 'Hard Gate (C-19): No Business Permission record is linked to this branch.';
        } elseif (($permission['status'] ?? '') !== 'GRANTED') {
            $violations[] = sprintf(
                'Hard Gate (C-19): Linked Business Permission status is "%s" (must be GRANTED).',
                (string) ($permission['status'] ?? 'UNKNOWN')
            );
        }

        // 4. Hard Quality Control & Verification Gate (C-21)
        if ($latestQcReview === null) {
            $violations[] = 'Hard Gate (C-21): Branch has not completed a Quality Control verification review.';
        } elseif (($latestQcReview['outcome'] ?? '') !== 'PASSED') {
            $violations[] = sprintf(
                'Hard Gate (C-21): Latest Quality Control review outcome is "%s" (must be PASSED).',
                (string) ($latestQcReview['outcome'] ?? 'UNKNOWN')
            );
        }

        return [
            'allowed'    => count($violations) === 0,
            'violations' => $violations,
        ];
    }
}
