<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Csrf;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Repositories\AreaRepository;
use Bulbula\Repositories\BranchRepository;
use Bulbula\Repositories\MediaRepository;
use Bulbula\Repositories\TaxonomyRepository;
use Bulbula\Services\MediaLifecycleService;
use Bulbula\Services\ListingLifecycleService;
use Bulbula\Services\StaffAuthService;

/**
 * C-19, C-20, C-22 Staff Listing Creation, Editing & Media Management Controller.
 */
final class ListingController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly BranchRepository $branchRepo = new BranchRepository(),
        private readonly MediaRepository $mediaRepo = new MediaRepository(),
        private readonly TaxonomyRepository $taxonomyRepo = new TaxonomyRepository(),
        private readonly AreaRepository $areaRepo = new AreaRepository(),
        private readonly MediaLifecycleService $mediaLifecycle = new MediaLifecycleService(),
        private readonly ListingLifecycleService $lifecycle = new ListingLifecycleService()
    ) {}

    public function index(Request $request): Response
    {
        $branches=$this->branchRepo->listAllForStaffConsole(50);
        foreach($branches as &$branch)$branch['active_media']=$this->mediaRepo->listActiveForBranch((int)$branch['branch_id']);
        unset($branch);
        $html = View::render('staff/listings', [
            'pageTitle'  => 'Listings & Permission Registry — Staff Console',
            'activeNav'  => 'listings',
            'staff'      => $this->staffAuth->currentStaff(),
            'branches'   => $branches,
            'categories' => $this->taxonomyRepo->getFullTree(false),
            'areas'      => $this->areaRepo->listAll(false),
        ], 'staff');

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/listings');
        }

        $staff = $this->staffAuth->currentStaff();
        $result = (new \Bulbula\Services\ListingOnboardingService())->onboard((array) $request->input(), (int) ($staff['id'] ?? 0));
        if (!$result['ok']) {
            Session::flash('error', (string) $result['error']);
            return Response::redirect('/staff/listings');
        }

        Session::flash('success', 'Listing recorded with explicit permission evidence and queued for quality control.');
        return Response::redirect('/staff/listings');
    }

    public function update(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/listings');
        }
        $staff = $this->staffAuth->currentStaff();
        $result = $this->lifecycle->updateVerifiedDetails((int)$request->param('branch_id', 0), (array)$request->input(), (int)($staff['id'] ?? 0));
        if (!$result['ok']) Session::flash('error', (string)$result['error']);
        elseif (!empty($result['reverification_required'])) Session::flash('success', 'Correction saved. The listing was removed from public discovery and returned to quality control.');
        else Session::flash('success', 'Draft listing details updated.');
        return Response::redirect('/staff/listings');
    }

    public function lifecycle(Request $request): Response
    {
        if (!Csrf::verify((string)$request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/listings');
        }
        $staff = $this->staffAuth->currentStaff();
        $result = $this->lifecycle->transition((int)$request->param('branch_id', 0), (string)$request->input('target_status', ''), (string)$request->input('reason', ''), (int)($staff['id'] ?? 0));
        if (!$result['ok']) Session::flash('error', (string)$result['error']);
        else Session::flash('success', 'Listing lifecycle updated. Reopening and republishing require quality-control approval.');
        return Response::redirect('/staff/listings');
    }

    public function uploadMedia(Request $request): Response
    {
        if (!Csrf::verify((string)$request->input('_csrf',''))) { Session::flash('error','Security token expired.'); return Response::redirect('/staff/listings'); }
        $staff=$this->staffAuth->currentStaff();
        $result=$this->mediaLifecycle->replaceCover(
            (int)$request->param('branch_id',0),
            $request->file('branch_image')??[],
            (string)$request->input('alt_text_en',''),
            (int)($staff['id']??0)
        );
        if(!$result['ok'])Session::flash('error','Media rejected: '.(string)($result['error']??'Operation failed.'));
        else Session::flash('success','Branch cover replaced through the active permission record.');
        return Response::redirect('/staff/listings');
    }

    public function removeMedia(Request $request): Response
    {
        if (!Csrf::verify((string)$request->input('_csrf',''))) { Session::flash('error','Security token expired.'); return Response::redirect('/staff/listings'); }
        $staff=$this->staffAuth->currentStaff();
        $result=$this->mediaLifecycle->remove((int)$request->param('branch_id',0),(int)$request->param('media_id',0),(string)$request->input('reason',''),(int)($staff['id']??0));
        Session::flash($result['ok']?'success':'error',$result['ok']?'Media removed safely.':(string)$result['error']);
        return Response::redirect('/staff/listings');
    }

    public function revokePermission(Request $request): Response
    {
        if (!Csrf::verify((string)$request->input('_csrf',''))) { Session::flash('error','Security token expired.'); return Response::redirect('/staff/listings'); }
        $staff=$this->staffAuth->currentStaff();
        $result=$this->mediaLifecycle->revokePermission((int)$request->param('branch_id',0),(string)$request->input('reason',''),(int)($staff['id']??0));
        Session::flash($result['ok']?'success':'error',$result['ok']?'Permission withdrawn; affected listings and media were removed from public use.':(string)$result['error']);
        return Response::redirect('/staff/listings');
    }

}
