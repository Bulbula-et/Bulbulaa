<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\View;
use Bulbula\Services\AuditService;
use Bulbula\Services\StaffAuthService;

/**
 * C-29 Administrator-Only Append-Only Audit Logs Controller.
 */
final class AuditLogController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly AuditService $auditService = new AuditService()
    ) {}

    public function index(Request $request): Response
    {
        $role = $this->staffAuth->currentRole();
        $logs = $role->canViewAuditLogs() ? $this->auditService->listLogsForAdmin(100) : [];

        $html = View::render('staff/audit-logs', [
            'pageTitle' => 'Append-Only Audit Logs — Staff Console',
            'activeNav' => 'audit-logs',
            'staff'     => $this->staffAuth->currentStaff(),
            'canView'   => $role->canViewAuditLogs(),
            'logs'      => $logs,
        ], 'staff');

        return Response::html($html);
    }
}
