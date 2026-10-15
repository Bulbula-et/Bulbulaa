<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Repositories\AuditLogRepository;

/**
 * Append-Only Audit Logging Service (C-29).
 */
final class AuditService
{
    public function __construct(
        private readonly AuditLogRepository $auditRepo = new AuditLogRepository()
    ) {}

    public function logStaffAction(
        int $staffId,
        string $actionCode,
        string $entityType,
        int $entityId,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null
    ): int {
        return $this->auditRepo->append('STAFF', $staffId, null, $actionCode, $entityType, $entityId, $before, $after, $reason);
    }

    public function logCustomerAction(
        int $customerId,
        string $actionCode,
        string $entityType,
        int $entityId,
        ?array $after = null,
        ?string $reason = null
    ): int {
        return $this->auditRepo->append('CUSTOMER', null, $customerId, $actionCode, $entityType, $entityId, null, $after, $reason);
    }

    public function listLogsForAdmin(int $limit = 100): array
    {
        return $this->auditRepo->listForAdministrator($limit);
    }

    public function listCustomerSecurityEvents(int $customerId, int $limit = 12): array
    {
        return $this->auditRepo->listForCustomer($customerId, $limit);
    }

    public function listStaffSecurityEvents(int $staffId, int $limit = 12): array
    {
        return $this->auditRepo->listForStaffMember($staffId, $limit);
    }
}
