<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Csrf;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Repositories\ReportRepository;
use Bulbula\Services\ModerationService;
use Bulbula\Services\StaffAuthService;

/**
 * C-26 Public Report & Correction Triage Controller.
 */
final class ReportQueueController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly ReportRepository $reportRepo = new ReportRepository(),
        private readonly ModerationService $moderationService = new ModerationService()
    ) {}

    public function index(Request $request): Response
    {
        $html = View::render('staff/reports', [
            'pageTitle' => 'Public Reports & Corrections Queue (C-26) — Staff Console',
            'activeNav' => 'reports',
            'staff'     => $this->staffAuth->currentStaff(),
            'reports'   => $this->reportRepo->listQueue(null, 100),
        ], 'staff');

        return Response::html($html);
    }

    public function resolve(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/reports');
        }

        $staff     = $this->staffAuth->currentStaff();
        $reportId  = (int) $request->param('report_id', 0);
        $newStatus = (string) $request->input('workflow_status', 'RESOLVED_UPDATED');
        $notes     = trim((string) $request->input('resolution_notes', 'Verified and updated branch record.'));

        $this->moderationService->resolvePublicReport($reportId, (int) ($staff['id'] ?? 1), $newStatus, $notes);
        Session::flash('success', 'Report #' . $reportId . ' updated to ' . $newStatus . '.');
        return Response::redirect('/staff/reports');
    }
}
