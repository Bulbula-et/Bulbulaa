<?php
declare(strict_types=1);

namespace Bulbula\Domain\Policies;

use Bulbula\Core\Config;
use DateTimeImmutable;

/**
 * Branch Review Policy (C-13, C-25, C-35, Page 3 & Page 6).
 *
 * Settled V1 Rules:
 * - A Review belongs strictly to a Branch.
 * - Rating is a required integer from 1 to 5; written text is optional.
 * - One active Review per Customer per Branch.
 * - Editing is permitted within 30 days of initial submission and re-enters PENDING_MODERATION.
 * - Published ratings use an unweighted mean of currently PUBLISHED reviews.
 */
final class ReviewPolicy
{
    /**
     * Validates a new or edited review submission payload.
     *
     * @return array{valid: bool, errors: string[], rating: int, sanitized_text: ?string}
     */
    public static function validateSubmission(mixed $rawRating, ?string $rawText): array
    {
        $errors = [];

        if (filter_var($rawRating, FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Rating must be a whole number between 1 and 5.';
            $rating = 0;
        } else {
            $rating = (int) $rawRating;
            if ($rating < 1 || $rating > 5) {
                $errors[] = 'Rating must be between 1 and 5 stars.';
            }
        }

        $text = $rawText !== null ? trim($rawText) : null;
        if ($text === '') {
            $text = null;
        }

        if ($text !== null && mb_strlen($text, 'UTF-8') > 2000) {
            $errors[] = 'Written review text cannot exceed 2,000 characters.';
        }

        return [
            'valid'          => count($errors) === 0,
            'errors'         => $errors,
            'rating'         => $rating,
            'sanitized_text' => $text,
        ];
    }

    /**
     * Checks whether an existing Review can still be edited by its Customer author (30-day rule).
     *
     * @return array{can_edit: bool, reason: ?string, days_remaining: int}
     */
    public static function canCustomerEdit(array $reviewRow, int $customerId, ?DateTimeImmutable $now = null): array
    {
        if ((int) ($reviewRow['customer_id'] ?? 0) !== $customerId) {
            return ['can_edit' => false, 'reason' => 'You can only edit your own review.', 'days_remaining' => 0];
        }

        $status = (string) ($reviewRow['status'] ?? '');
        if (in_array($status, ['WITHDRAWN_BY_AUTHOR', 'REMOVED_BY_STAFF'], true)) {
            return ['can_edit' => false, 'reason' => 'Withdrawn or removed reviews cannot be edited.', 'days_remaining' => 0];
        }

        $windowDays  = (int) Config::get('security.review_edit_window_days', 30);
        $submittedAt = new DateTimeImmutable((string) $reviewRow['first_submitted_at']);
        $now         = $now ?? new DateTimeImmutable('now');

        $elapsedSeconds = $now->getTimestamp() - $submittedAt->getTimestamp();
        $maxSeconds     = $windowDays * 86400;

        if ($elapsedSeconds > $maxSeconds) {
            return [
                'can_edit'       => false,
                'reason'         => sprintf('Reviews can only be edited within %d days of submission.', $windowDays),
                'days_remaining' => 0,
            ];
        }

        $remainingDays = (int) ceil(($maxSeconds - $elapsedSeconds) / 86400);
        return [
            'can_edit'       => true,
            'reason'         => null,
            'days_remaining' => max(0, $remainingDays),
        ];
    }

    /**
     * Computes the unweighted mean rating across currently PUBLISHED review ratings (C-13).
     *
     * @param int[] $publishedRatings
     * @return array{average: ?float, count: int}
     */
    public static function computeUnweightedSummary(array $publishedRatings): array
    {
        $count = count($publishedRatings);
        if ($count === 0) {
            return ['average' => null, 'count' => 0];
        }
        $sum = array_sum($publishedRatings);
        return [
            'average' => round($sum / $count, 2),
            'count'   => $count,
        ];
    }
}
