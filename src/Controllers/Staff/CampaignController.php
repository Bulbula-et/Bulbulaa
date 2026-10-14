<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Cache;
use Bulbula\Core\Csrf;
use Bulbula\Core\Database;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Repositories\BranchRepository;
use Bulbula\Repositories\GateRepository;
use Bulbula\Repositories\SponsorshipRepository;
use Bulbula\Services\AuditService;
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
        private readonly BranchRepository $branchRepo = new BranchRepository(),
        private readonly GateRepository $gateRepo = new GateRepository(),
        private readonly CampaignService $campaignService = new CampaignService(),
        private readonly AuditService $auditService = new AuditService()
    ) {}

    public function index(Request $request): Response
    {
        $html = View::render('staff/campaigns', [
            'pageTitle'   => 'Sponsored Placements & Campaigns — Staff Console',
            'activeNav'   => 'campaigns',
            'staff'       => $this->staffAuth->currentStaff(),
            'campaigns'   => $this->sponsorshipRepo->listAllForStaff(),
            'branches'    => $this->branchRepo->listAllForStaffConsole(50),
            'gateD39Open' => $this->gateRepo->isEnabled('D_39_PAID_CAMPAIGNS_ENABLED', false),
        ], 'staff');

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/campaigns');
        }

        $branchId = (int) $request->input('branch_id', 0);
        $format   = (string) $request->input('placement_format', 'HOMEPAGE_PROMOTION');
        $priceEtb = is_numeric($request->input('agreed_price_etb')) ? (float) $request->input('agreed_price_etb') : 4500.00;
        $days     = max(1, min(365, (int) $request->input('duration_days', 30)));

        $branch = $this->branchRepo->findByIdForStaff($branchId);
        if ($branch === null) {
            Session::flash('error', 'Please select a valid published branch.');
            return Response::redirect('/staff/campaigns');
        }

        $staff   = $this->staffAuth->currentStaff();
        $staffId = (int) ($staff['id'] ?? 1);
        $code    = 'SP-' . date('Ym') . '-' . random_int(100, 999);
        $starts  = date('Y-m-d H:i:s');
        $ends    = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));

        $stmt = Database::pdo()->prepare(
            'INSERT INTO sponsorship_campaigns (campaign_code, business_id, branch_id, placement_format, target_category_id, package_name, agreed_price_etb, starts_at, ends_at, status, created_by_staff_id)
             VALUES (:code, :biz_id, :br_id, :fmt, :cat_id, :pkg, :price, :starts, :ends, :status, :staff_id)'
        );
        $stmt->execute([
            ':code'     => $code,
            ':biz_id'   => (int) $branch['business_id'],
            ':br_id'    => $branchId,
            ':fmt'      => in_array($format, ['HOMEPAGE_PROMOTION', 'CATEGORY_SPONSORSHIP', 'SEARCH_PLACEMENT'], true) ? $format : 'HOMEPAGE_PROMOTION',
            ':cat_id'   => $format !== 'HOMEPAGE_PROMOTION' ? (int) ($branch['primary_category_id'] ?? 1) : null,
            ':pkg'      => $days . '-Day Fixed Placement',
            ':price'    => $priceEtb,
            ':starts'   => $starts,
            ':ends'     => $ends,
            ':status'   => 'PENDING_ADMIN_APPROVAL',
            ':staff_id' => $staffId,
        ]);
        $newId = (int) Database::pdo()->lastInsertId();

        $this->auditService->logStaffAction(
            $staffId,
            'CAMPAIGN_RECORDED_PENDING_APPROVAL',
            'sponsorship_campaign',
            $newId,
            null,
            ['campaign_code' => $code, 'placement_format' => $format, 'agreed_price_etb' => $priceEtb]
        );
        Cache::flushAll();

        Session::flash('success', 'Campaign ' . $code . ' recorded in PENDING_ADMIN_APPROVAL. An Administrator can now approve it.');
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
