<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Core\Cache;
use Bulbula\Core\Database;
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
        private readonly AuditService $auditService = new AuditService(),
        private readonly StaffCampaignMailer $mailer = new StaffCampaignMailer()
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

    public function terminateEarly(int $campaignId,int $adminId,string $reason):array
    {
        $campaign=$this->sponsorshipRepo->findById($campaignId);$reason=trim($reason);
        if($campaign===null)return ['ok'=>false,'error'=>'Campaign not found.'];
        if(!in_array((string)$campaign['status'],['ACTIVE','APPROVED_SCHEDULED'],true))return ['ok'=>false,'error'=>'Only an active or approved scheduled campaign can be terminated early.'];
        if(mb_strlen($reason)<10||mb_strlen($reason)>500)return ['ok'=>false,'error'=>'Provide a termination reason of 10–500 characters.'];
        $pdo=Database::pdo();
        try{$pdo->beginTransaction();if(!$this->sponsorshipRepo->terminateEarly($campaignId,$adminId,$reason))throw new \RuntimeException('Campaign state changed.');$this->auditService->logStaffAction($adminId,'CAMPAIGN_TERMINATED_EARLY','sponsorship_campaign',$campaignId,['status'=>$campaign['status']],['status'=>'TERMINATED_EARLY','historical_metrics_preserved'=>true],$reason);$pdo->commit();}
        catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();return ['ok'=>false,'error'=>'Campaign termination failed without changing delivery.'];}
        Cache::invalidateTags(['campaigns']);
        try{$campaign['termination_reason']=$reason;$mail=$this->mailer->terminated($campaign);$this->auditService->logStaffAction($adminId,!empty($mail['ok'])?'CAMPAIGN_TERMINATION_NOTICE_SENT':'CAMPAIGN_TERMINATION_NOTICE_FAILED','sponsorship_campaign',$campaignId,null,['recipient_role'=>'campaign_creator','delivery_status'=>!empty($mail['ok'])?'SENT':'FAILED']);}catch(\Throwable $e){error_log('Campaign termination notice failed safely for campaign #'.$campaignId.'.');}
        return ['ok'=>true];
    }
}
