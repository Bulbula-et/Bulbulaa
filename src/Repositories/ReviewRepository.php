<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Cache;
use Bulbula\Core\Database;
use Bulbula\Domain\Policies\ReviewPolicy;

/**
 * Branch Review Repository (C-13, C-25, C-35).
 * Enforces pre-publication moderation, newest-first sorting, and unweighted mean recalculation.
 */
final class ReviewRepository
{
    /**
     * C-13 List PUBLISHED reviews for a Branch, sorted newest-first by default.
     */
    public function listPublishedForBranch(int $branchId, int $limit = 30): array
    {
        return Cache::remember('reviews:branch:' . $branchId, 300, ['reviews'], static function () use ($branchId, $limit): array {
            $safeLimit = max(1, min(100, $limit));
            return Database::fetchAll(
                "SELECT r.id, r.branch_id, r.rating, r.review_text, r.first_submitted_at, r.last_edited_at,
                        c.display_name AS author_name
                 FROM reviews r
                 INNER JOIN customers c ON c.id = r.customer_id
                 WHERE r.branch_id = :bid AND r.status = 'PUBLISHED'
                 ORDER BY r.first_submitted_at DESC, r.id DESC
                 LIMIT {$safeLimit}",
                [':bid' => $branchId]
            );
        });
    }

    public function findActiveCustomerReviewForBranch(int $branchId, int $customerId): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM reviews
             WHERE branch_id = :bid AND customer_id = :cid
               AND status IN ('PENDING_MODERATION', 'PUBLISHED', 'REJECTED')
             ORDER BY id DESC
             LIMIT 1",
            [':bid' => $branchId, ':cid' => $customerId]
        );
    }

    public function branchExists(int $branchId): bool
    {
        if ($branchId <= 0) return false;
        return Database::fetchOne('SELECT id FROM branches WHERE id = :id LIMIT 1', [':id' => $branchId]) !== null;
    }

    public function findById(int $reviewId): ?array
    {
        return Database::fetchOne('SELECT * FROM reviews WHERE id = :id LIMIT 1', [':id' => $reviewId]);
    }

    /**
     * C-35 List all reviews authored by a specific Customer with Branch & moderation status.
     */
    public function listByCustomer(int $customerId): array
    {
        return Database::fetchAll(
            'SELECT r.*, br.branch_name_en, br.branch_slug, bz.name_en AS business_name_en, bz.slug AS business_slug
             FROM reviews r
             INNER JOIN branches br ON br.id = r.branch_id
             INNER JOIN businesses bz ON bz.id = br.business_id
             WHERE r.customer_id = :cid
             ORDER BY r.first_submitted_at DESC',
            [':cid' => $customerId]
        );
    }

    /**
     * C-25 Staff Moderation Queue (new and edited reviews in PENDING_MODERATION).
     */
    public function listModerationQueue(string $statusFilter = 'PENDING_MODERATION', int $limit = 100): array
    {
        $safeLimit = max(1, min(200, $limit));
        return Database::fetchAll(
            "SELECT r.*, c.display_name AS author_name, c.email AS author_email,
                    br.branch_name_en, br.branch_slug, bz.name_en AS business_name_en, bz.slug AS business_slug
             FROM reviews r
             INNER JOIN customers c ON c.id = r.customer_id
             INNER JOIN branches br ON br.id = r.branch_id
             INNER JOIN businesses bz ON bz.id = br.business_id
             WHERE r.status = :status
             ORDER BY r.first_submitted_at ASC
             LIMIT {$safeLimit}",
            [':status' => $statusFilter]
        );
    }

    public function createPendingReview(int $branchId, int $customerId, int $rating, ?string $reviewText): int
    {
        $id = Database::insert(
            "INSERT INTO reviews (branch_id, customer_id, rating, review_text, status, edit_count)
             VALUES (:bid, :cid, :rating, :text, 'PENDING_MODERATION', 0)",
            [
                ':bid'    => $branchId,
                ':cid'    => $customerId,
                ':rating' => $rating,
                ':text'   => $reviewText,
            ]
        );
        Cache::invalidateTags(['reviews']);
        return $id;
    }

    /**
     * C-13, C-35 Edit within 30 days: returns review to PENDING_MODERATION and recalculates branch summary.
     */
    public function updateAndReturnToModeration(int $reviewId, int $branchId, int $rating, ?string $reviewText): void
    {
        Database::transaction(function () use ($reviewId, $branchId, $rating, $reviewText): void {
            Database::execute(
                "UPDATE reviews
                 SET rating = :rating,
                     review_text = :text,
                     status = 'PENDING_MODERATION',
                     edit_count = edit_count + 1,
                     last_edited_at = CURRENT_TIMESTAMP
                 WHERE id = :id",
                [
                    ':rating' => $rating,
                    ':text'   => $reviewText,
                    ':id'     => $reviewId,
                ]
            );
            $this->recalculateBranchRatingSummary($branchId);
        });

        Cache::invalidateTags(['reviews', 'branches']);
    }

    /**
     * C-25 Staff moderation decision (PUBLISHED, REJECTED, REMOVED_BY_STAFF).
     */
    public function applyModerationDecision(
        int $reviewId,
        int $branchId,
        string $newStatus,
        int $moderatorStaffId,
        string $reasonCode,
        ?string $notes = null
    ): void {
        Database::transaction(function () use ($reviewId, $branchId, $newStatus, $moderatorStaffId, $reasonCode, $notes): void {
            Database::execute(
                'UPDATE reviews
                 SET status = :status,
                     moderated_by_staff_id = :staff_id,
                     moderated_at = CURRENT_TIMESTAMP,
                     moderation_reason_code = :reason,
                     moderation_notes = :notes
                 WHERE id = :id',
                [
                    ':status'   => $newStatus,
                    ':staff_id' => $moderatorStaffId,
                    ':reason'   => $reasonCode,
                    ':notes'    => $notes,
                    ':id'       => $reviewId,
                ]
            );
            $this->recalculateBranchRatingSummary($branchId);
        });

        Cache::invalidateTags(['reviews', 'branches']);
    }

    /**
     * C-35 Customer withdraws own review so it stops being publicly visible.
     */
    public function withdrawByAuthor(int $reviewId, int $branchId, int $customerId): void
    {
        Database::transaction(function () use ($reviewId, $branchId, $customerId): void {
            Database::execute(
                "UPDATE reviews
                 SET status = 'WITHDRAWN_BY_AUTHOR',
                     withdrawn_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND customer_id = :cid",
                [':id' => $reviewId, ':cid' => $customerId]
            );
            $this->recalculateBranchRatingSummary($branchId);
        });

        Cache::invalidateTags(['reviews', 'branches']);
    }

    /**
     * C-13 Recalculates the unweighted mean of currently PUBLISHED ratings for a Branch.
     */
    public function recalculateBranchRatingSummary(int $branchId): array
    {
        $rows = Database::fetchAll(
            "SELECT rating FROM reviews WHERE branch_id = :bid AND status = 'PUBLISHED'",
            [':bid' => $branchId]
        );
        $ratings = array_map(static fn(array $r): int => (int) $r['rating'], $rows);
        $summary = ReviewPolicy::computeUnweightedSummary($ratings);

        Database::execute(
            'UPDATE branches
             SET published_rating_avg = :avg,
                 published_review_count = :cnt
             WHERE id = :bid',
            [
                ':avg' => $summary['average'],
                ':cnt' => $summary['count'],
                ':bid' => $branchId,
            ]
        );

        return $summary;
    }
}
