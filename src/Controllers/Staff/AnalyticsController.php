<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\View;
use Bulbula\Services\AnalyticsService;
use Bulbula\Services\StaffAuthService;

/**
 * C-28 Operational Analytics Controller.
 */
final class AnalyticsController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly AnalyticsService $analyticsService = new AnalyticsService()
    ) {}

    public function index(Request $request): Response
    {
        $html = View::render('staff/analytics', [
            'pageTitle' => 'Operational & Pilot Analytics — Staff Console',
            'activeNav' => 'analytics',
            'staff'     => $this->staffAuth->currentStaff(),
            'summary'   => $this->analyticsService->getOperationalDashboardSummary(),
        ], 'staff');

        return Response::html($html);
    }
}
