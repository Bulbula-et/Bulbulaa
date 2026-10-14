<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Database;

/**
 * Public Reports & Correction Suggestions Repository (C-15, C-26).
 */
final class ReportRepository
{
    public function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO public_reports (
                target_type, branch_id, business_id, review_id,
                reporter_customer_id, reporter_contact_email,
                issue_category, details_text, workflow_status
            ) VALUES (
                :target_type, :branch_id, :business_id, :review_id,
                :cust_id, :email, :category, :details, :status
            )',
            [
                ':target_type' => (string) $data['target_type'],
                ':branch_id'   => !empty($data['branch_id']) ? (int) $data['branch_id'] : null,
                ':business_id' => !empty($data['business_id']) ? (int) $data['business_id'] : null,
                ':review_id'   => !empty($data['review_id']) ? (int) $data['review_id'] : null,
                ':cust_id'     => !empty($data['reporter_customer_id']) ? (int) $data['reporter_customer_id'] : null,
                ':email'       => !empty($data['reporter_contact_email']) ? trim((string) $data['reporter_contact_email']) : null,
                ':category'    => (string) $data['issue_category'],
                ':details'     => trim((string) $data['details_text']),
                ':status'      => 'OPEN',
            ]
        );
    }

    public function listQueue(?string $statusFilter = null, int $limit = 100): array
    {
        $safeLimit = max(1, min(200, $limit));
        $where     = $statusFilter ? 'WHERE pr.workflow_status = :status' : '';
        $params    = $statusFilter ? [':status' => $statusFilter] : [];

        return Database::fetchAll(
            "SELECT pr.*,
                    br.branch_name_en, br.branch_slug,
                    bz.name_en AS business_name_en, bz.slug AS business_slug
             FROM public_reports pr
             LEFT JOIN branches br ON br.id = pr.branch_id
             LEFT JOIN businesses bz ON bz.id = COALESCE(pr.business_id, br.business_id)
             {$where}
             ORDER BY pr.created_at ASC
             LIMIT {$safeLimit}",
            $params
        );
    }

    public function resolveReport(int $reportId, string $newStatus, int $staffId, string $resolutionNotes): void
    {
        Database::execute(
            'UPDATE public_reports
             SET workflow_status = :status,
                 handled_by_staff_id = :staff_id,
                 resolution_notes = :notes,
                 resolved_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            [
                ':status'   => $newStatus,
                ':staff_id' => $staffId,
                ':notes'    => trim($resolutionNotes),
                ':id'       => $reportId,
            ]
        );
    }
}
