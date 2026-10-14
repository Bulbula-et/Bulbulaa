<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Domain\Policies\PublicationGate;
use Bulbula\Repositories\BranchRepository;
use Bulbula\Repositories\BusinessRepository;
use Bulbula\Repositories\PermissionRepository;

/**
 * Internal Listing Operations & Quality Control Service (C-19, C-20, C-21, C-22).
 * Enforces the hard PublicationGate before any branch can become PUBLISHED.
 */
final class ListingOpsService
{
    public function __construct(
        private readonly BusinessRepository $businessRepo = new BusinessRepository(),
        private readonly BranchRepository $branchRepo = new BranchRepository(),
        private readonly PermissionRepository $permissionRepo = new PermissionRepository(),
        private readonly AuditService $auditService = new AuditService()
    ) {}

    /**
     * C-21 Executes QC Verification Review and, if PASSED and Permission is GRANTED, transitions Branch to PUBLISHED.
     */
    public function performQcAndPublish(
        int $branchId,
        int $reviewerStaffId,
        string $outcome,
        array $checklist,
        ?string $notes = null
    ): array {
        $branch = $this->branchRepo->findByIdForStaff($branchId);
        if ($branch === null) {
            return ['ok' => false, 'errors' => ['Branch not found.']];
        }

        $businessId = (int) $branch['business_id'];
        $business   = $this->businessRepo->findById($businessId);
        $permission = $this->permissionRepo->findActiveForBusiness($businessId);

        if ($permission === null) {
            return [
                'ok'     => false,
                'errors' => ['Cannot record quality control or publish without a granted business permission record.'],
            ];
        }

        $qcId = $this->permissionRepo->recordQcReview([
            'branch_id'             => $branchId,
            'permission_id'         => (int) $permission['id'],
            'submitted_by_staff_id' => $reviewerStaffId,
            'reviewed_by_staff_id'  => $reviewerStaffId,
            'outcome'               => $outcome,
            'checklist_json'        => $checklist,
            'reviewer_notes'        => $notes,
        ]);

        $latestQc = $this->permissionRepo->latestQcForBranch($branchId);
        $gateEval = PublicationGate::evaluate((array) $business, $branch, $permission, $latestQc);

        if ($outcome === 'PASSED' && $gateEval['allowed']) {
            $this->branchRepo->updateStatusAndVerification(
                $branchId,
                'PUBLISHED',
                true,
                (int) $permission['id'],
                $reviewerStaffId
            );

            $this->auditService->logStaffAction(
                $reviewerStaffId,
                'BRANCH_QC_PASSED_AND_PUBLISHED',
                'branch',
                $branchId,
                ['publication_status' => $branch['publication_status']],
                ['publication_status' => 'PUBLISHED', 'is_verified' => 1, 'qc_id' => $qcId],
                $notes
            );

            return ['ok' => true, 'published' => true, 'violations' => []];
        }

        $newStatus = $outcome === 'RETURNED_FOR_FIXES' ? 'DRAFT' : 'PENDING_QC';
        $this->branchRepo->updateStatusAndVerification(
            $branchId,
            $newStatus,
            false,
            (int) $permission['id'],
            $reviewerStaffId
        );

        $this->auditService->logStaffAction(
            $reviewerStaffId,
            'BRANCH_QC_RECORDED',
            'branch',
            $branchId,
            ['publication_status' => $branch['publication_status']],
            ['publication_status' => $newStatus, 'outcome' => $outcome, 'qc_id' => $qcId],
            $notes
        );

        return ['ok' => true, 'published' => false, 'violations' => $gateEval['violations']];
    }
}
