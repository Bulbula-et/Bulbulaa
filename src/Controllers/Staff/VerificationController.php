<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Csrf;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Repositories\BranchRepository;
use Bulbula\Repositories\PermissionRepository;
use Bulbula\Repositories\StaffRepository;
use Bulbula\Services\ListingOpsService;
use Bulbula\Services\StaffAuthService;

/**
 * C-21 Verification & Quality Control Queue Controller.
 */
final class VerificationController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly BranchRepository $branchRepo = new BranchRepository(),
        private readonly PermissionRepository $permissionRepo = new PermissionRepository(),
        private readonly StaffRepository $staffRepo = new StaffRepository(),
        private readonly ListingOpsService $listingOps = new ListingOpsService()
    ) {}

    public function index(Request $request): Response
    {
        $staff = $this->staffAuth->currentStaff();
        $branches = $this->branchRepo->listAllForStaffConsole(50);
        foreach ($branches as &$br) {
            $br['permission'] = $this->permissionRepo->findActiveForBusiness((int) $br['business_id']);
            $br['latest_qc']  = $this->permissionRepo->latestQcForBranch((int) $br['branch_id']);
            $preparerId = $this->branchRepo->preparerStaffId((int)$br['branch_id']);
            $br['preparer'] = $preparerId ? $this->staffRepo->findByIdAnyStatus($preparerId) : null;
            $br['self_review_blocked'] = $preparerId === (int)($staff['id']??0) && $this->staffRepo->hasOtherActiveReviewer((int)($staff['id']??0));
        }

        $html = View::render('staff/verification', [
            'pageTitle' => 'Verification & Quality Control — Staff Console',
            'activeNav' => 'verification',
            'staff'     => $staff,
            'branches'  => $branches,
        ], 'staff');

        return Response::html($html);
    }

    public function recordQc(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/verification');
        }

        $staff    = $this->staffAuth->currentStaff();
        $branchId = (int) $request->param('branch_id', 0);
        $outcome  = (string) $request->input('outcome', '');
        $notes    = trim((string) $request->input('reviewer_notes', ''));
        $checklist = [
            'permission_verified'=>(string)$request->input('permission_verified','')==='1',
            'coordinates_checked'=>(string)$request->input('coordinates_checked','')==='1',
            'category_checked'=>(string)$request->input('category_checked','')==='1',
        ];

        $result = $this->listingOps->performQcAndPublish(
            $branchId,
            (int) ($staff['id'] ?? 0),
            $outcome,
            $checklist,
            $notes
        );

        if ($result['ok'] && $result['published']) {
            Session::flash('success', 'Branch #' . $branchId . ' passed quality control and is now published and verified.');
        } elseif ($result['ok']) {
            Session::flash('success', 'Quality-control decision recorded. The Branch was not published.');
        } else {
            Session::flash('error', implode(' ', $result['errors'] ?? ['Quality control could not be recorded.']));
        }

        return Response::redirect('/staff/verification');
    }
}
