<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Domain\Enums\StaffRole;
use Bulbula\Domain\Policies\SponsorshipPolicy;
use Bulbula\Repositories\BranchRepository;
use Bulbula\Repositories\GateRepository;
use Bulbula\Repositories\SponsorshipRepository;

/**
 * Staff-Managed Sponsorship Campaign Service (C-16, C-27, Section 07, Gate D-39).
 */
final class CampaignService
{
    public function __construct(
        private readonly SponsorshipRepository $sponsorshipRepo = new SponsorshipRepository(),
        private readonly BranchRepository $branchRepo = new BranchRepository(),
        private readonly GateRepository $gateRepo = new GateRepository(),
        private readonly AuditService $auditService = new AuditService()
    ) {}

    public function approveCampaign(int $campaignId, int $staffId, StaffRole $staffRole): array
    {
        $campaign = $this->sponsorshipRepo->findById($campaignId);
        if ($campaign === null) {
            return ['ok' => false, 'errors' => ['Campaign not found.']];
        }

        $branch = $this->branchRepo->findByIdForStaff((int) $campaign['branch_id']);
        if ($branch === null) {
            return ['ok' => false, 'errors' => ['Target branch not found.']];
        }

        $gateD39 = $this->gateRepo->isEnabled('D_39_PAID_CAMPAIGNS_ENABLED', false);
        $eval    = SponsorshipPolicy::canApproveCampaign($campaign, $branch, $staffRole, $gateD39);

        if (!$eval['allowed']) {
            return ['ok' => false, 'errors' => $eval['violations']];
        }

        $this->sponsorshipRepo->approveAndActivate($campaignId, $staffId);
        $this->auditService->logStaffAction(
            $staffId,
            'CAMPAIGN_APPROVED_ACTIVE',
            'sponsorship_campaign',
            $campaignId,
            ['status' => $campaign['status']],
            ['status' => 'ACTIVE']
        );

        return ['ok' => true, 'errors' => []];
    }
}
