<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Repositories\ReportRepository;
use Bulbula\Repositories\ReviewRepository;

/**
 * Review Moderation & Public Report Triage Service (C-25, C-26).
 */
final class ModerationService
{
    public function __construct(
        private readonly ReviewRepository $reviewRepo = new ReviewRepository(),
        private readonly ReportRepository $reportRepo = new ReportRepository(),
        private readonly AuditService $auditService = new AuditService()
    ) {}

    public function decideReview(
        int $reviewId,
        int $staffId,
        string $decisionStatus,
        string $reasonCode,
        ?string $notes = null
    ): array {
        $review = $this->reviewRepo->findById($reviewId);
        if ($review === null) {
            return ['ok' => false, 'error' => 'Review not found.'];
        }

        if (!in_array($decisionStatus, ['PUBLISHED', 'REJECTED', 'REMOVED_BY_STAFF'], true)) {
            return ['ok' => false, 'error' => 'Invalid moderation decision status.'];
        }

        $branchId = (int) $review['branch_id'];
        $this->reviewRepo->applyModerationDecision(
            $reviewId,
            $branchId,
            $decisionStatus,
            $staffId,
            $reasonCode,
            $notes
        );

        $this->auditService->logStaffAction(
            $staffId,
            'REVIEW_MODERATED_' . $decisionStatus,
            'review',
            $reviewId,
            ['status' => $review['status']],
            ['status' => $decisionStatus, 'reason_code' => $reasonCode],
            $notes
        );

        return ['ok' => true];
    }

    public function resolvePublicReport(int $reportId, int $staffId, string $newStatus, string $notes): void
    {
        $this->reportRepo->resolveReport($reportId, $newStatus, $staffId, $notes);
        $this->auditService->logStaffAction(
            $staffId,
            'PUBLIC_REPORT_' . $newStatus,
            'public_report',
            $reportId,
            null,
            ['workflow_status' => $newStatus],
            $notes
        );
    }
}
