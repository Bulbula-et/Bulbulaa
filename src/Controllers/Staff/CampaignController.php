<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Csrf;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Repositories\GateRepository;
use Bulbula\Repositories\SponsorshipRepository;
use Bulbula\Services\CampaignService;
use Bulbula\Services\StaffAuthService;

/**
 * C-27 Staff-Managed Sponsorship Campaigns Controller.
 */
final class CampaignController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly SponsorshipRepository $sponsorshipRepo = new SponsorshipRepository(),
        private readonly GateRepository $gateRepo = new GateRepository(),
        private readonly CampaignService $campaignService = new CampaignService()
    ) {}

    public function index(Request $request): Response
    {
        $html = View::render('staff/campaigns', [
            'pageTitle'   => 'Sponsored Placements & Campaigns (C-27) — Staff Console',
            'activeNav'   => 'campaigns',
            'staff'       => $this->staffAuth->currentStaff(),
            'campaigns'   => $this->sponsorshipRepo->listAllForStaff(),
            'gateD39Open' => $this->gateRepo->isEnabled('D_39_PAID_CAMPAIGNS_ENABLED', false),
        ], 'staff');

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        return Response::redirect('/staff/campaigns');
    }

    public function approve(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/campaigns');
        }

        $staff = $this->staffAuth->currentStaff();
        $role  = $this->staffAuth->currentRole();
        $id    = (int) $request->param('id', 0);

        $res = $this->campaignService->approveCampaign($id, (int) ($staff['id'] ?? 1), $role);
        if ($res['ok']) {
            Session::flash('success', 'Campaign #' . $id . ' approved by Administrator and activated.');
        } else {
            Session::flash('error', implode(' ', $res['errors']));
        }

        return Response::redirect('/staff/campaigns');
    }
}
