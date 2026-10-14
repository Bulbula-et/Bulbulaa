<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Repositories\AnalyticsRepository;
use Bulbula\Repositories\SponsorshipRepository;

/**
 * First-Party Server-Side Analytics Service (C-28, C-38).
 */
final class AnalyticsService
{
    public function __construct(
        private readonly AnalyticsRepository $analyticsRepo = new AnalyticsRepository(),
        private readonly SponsorshipRepository $sponsorshipRepo = new SponsorshipRepository()
    ) {}

    public function recordBranchContactOrShare(int $branchId, string $surface, string $actionType, ?int $campaignId = null): void
    {
        $map = [
            'call'       => 'action_call_clicks',
            'directions' => 'action_map_clicks',
            'website'    => 'action_website_clicks',
            'email'      => 'action_email_clicks',
            'share'      => 'action_share_clicks',
        ];

        if (isset($map[$actionType])) {
            $this->analyticsRepo->incrementBranchAction($branchId, $surface, $map[$actionType]);
        }

        if ($campaignId !== null && $campaignId > 0) {
            $this->sponsorshipRepo->incrementMetric($campaignId, $surface, 'CLICK');
        }
    }

    public function getOperationalDashboardSummary(): array
    {
        return $this->analyticsRepo->getOperationalSummary();
    }
}
