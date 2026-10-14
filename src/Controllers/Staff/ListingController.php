<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\View;
use Bulbula\Repositories\AreaRepository;
use Bulbula\Repositories\BranchRepository;
use Bulbula\Repositories\TaxonomyRepository;
use Bulbula\Services\StaffAuthService;

/**
 * C-19, C-20, C-22 Staff Listing Creation, Editing & Media Management Controller.
 */
final class ListingController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly BranchRepository $branchRepo = new BranchRepository(),
        private readonly TaxonomyRepository $taxonomyRepo = new TaxonomyRepository(),
        private readonly AreaRepository $areaRepo = new AreaRepository()
    ) {}

    public function index(Request $request): Response
    {
        $html = View::render('staff/listings', [
            'pageTitle'  => 'Listings & Permission Registry (C-19, C-20) — Staff Console',
            'activeNav'  => 'listings',
            'staff'      => $this->staffAuth->currentStaff(),
            'branches'   => $this->branchRepo->listAllForStaffConsole(50),
            'categories' => $this->taxonomyRepo->getFullTree(false),
            'areas'      => $this->areaRepo->listAll(false),
        ], 'staff');

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        return Response::redirect('/staff/listings');
    }

    public function update(Request $request): Response
    {
        return Response::redirect('/staff/listings');
    }

    public function uploadMedia(Request $request): Response
    {
        return Response::redirect('/staff/listings');
    }
}
