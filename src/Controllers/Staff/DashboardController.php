<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Cache;
use Bulbula\Core\Csrf;
use Bulbula\Core\LiveReload;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Repositories\BranchRepository;
use Bulbula\Repositories\GateRepository;
use Bulbula\Services\AnalyticsService;
use Bulbula\Services\AuditService;
use Bulbula\Services\StaffAuthService;

/**
 * Staff Console Dashboard & Zero-Restart Cache Flush Controller (C-19..C-29).
 */
final class DashboardController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly AnalyticsService $analyticsService = new AnalyticsService(),
        private readonly BranchRepository $branchRepo = new BranchRepository(),
        private readonly GateRepository $gateRepo = new GateRepository(),
        private readonly AuditService $auditService = new AuditService()
    ) {}

    public function index(Request $request): Response
    {
        if ($request->query('switch_staff')) {
            $this->staffAuth->switchPreviewStaffProfile((int) $request->query('switch_staff'));
            return Response::redirect('/staff');
        }

        $staff   = $this->staffAuth->currentStaff();
        $summary = $this->analyticsService->getOperationalDashboardSummary();
        $gates   = $this->gateRepo->allIndexed();

        $html = View::render('staff/dashboard', [
            'pageTitle' => 'Staff Operations Console — Bulbula.et V1',
            'activeNav' => 'dashboard',
            'staff'     => $staff,
            'summary'   => $summary,
            'gates'     => $gates,
            'branches'  => $this->branchRepo->listAllForStaffConsole(15),
        ], 'staff');

        return Response::html($html);
    }

    /**
     * One-click zero-restart File Cache & OPcache Refresh from Staff Console.
     */
    public function flushCache(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff');
        }

        $staff = $this->staffAuth->currentStaff();
        $stats = Cache::flushAll();
        $opcacheCount = LiveReload::forceFlushAll(BULBULA_ROOT);

        if ($staff !== null) {
            $this->auditService->logStaffAction(
                (int) $staff['id'],
                'SYSTEM_CACHE_AND_OPCACHE_FLUSHED',
                'system_cache',
                1,
                null,
                ['new_global_epoch' => $stats['new_global_epoch'], 'opcache_files_invalidated' => $opcacheCount]
            );
        }

        Session::flash(
            'success',
            sprintf(
                'Cache refreshed without server restart (Epoch #%d, %d cache files cleared, %d OPcache scripts invalidated).',
                $stats['new_global_epoch'],
                $stats['files_removed'],
                $opcacheCount
            )
        );
        return Response::redirect('/staff');
    }
}
