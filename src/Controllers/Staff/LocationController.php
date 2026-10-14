<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\View;
use Bulbula\Repositories\AreaRepository;
use Bulbula\Services\StaffAuthService;

/**
 * C-24 Controlled Local Area & Sub-City Management Controller.
 */
final class LocationController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly AreaRepository $areaRepo = new AreaRepository()
    ) {}

    public function index(Request $request): Response
    {
        $html = View::render('staff/locations', [
            'pageTitle' => 'Local Areas & Launch Boundary (C-24, D-40) — Staff Console',
            'activeNav' => 'locations',
            'staff'     => $this->staffAuth->currentStaff(),
            'areas'     => $this->areaRepo->listAll(false),
        ], 'staff');

        return Response::html($html);
    }

    public function save(Request $request): Response
    {
        return Response::redirect('/staff/locations');
    }
}
