<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\View;
use Bulbula\Repositories\TaxonomyRepository;
use Bulbula\Services\StaffAuthService;

/**
 * C-23 Controlled 2-Level Category Management Controller.
 */
final class TaxonomyController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly TaxonomyRepository $taxonomyRepo = new TaxonomyRepository()
    ) {}

    public function index(Request $request): Response
    {
        $html = View::render('staff/taxonomy', [
            'pageTitle'  => '2-Level Taxonomy Catalogue (C-23) — Staff Console',
            'activeNav'  => 'taxonomy',
            'staff'      => $this->staffAuth->currentStaff(),
            'categories' => $this->taxonomyRepo->getFullTree(false),
        ], 'staff');

        return Response::html($html);
    }

    public function save(Request $request): Response
    {
        return Response::redirect('/staff/taxonomy');
    }
}
