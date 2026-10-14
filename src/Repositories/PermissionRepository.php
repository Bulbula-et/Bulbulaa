<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Cache;
use Bulbula\Core\Database;

/**
 * Business Permissions & Verification Quality Control Repository (C-19, C-21).
 */
final class PermissionRepository
{
    public function findById(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM business_permissions WHERE id = :id LIMIT 1', [':id' => $id]);
    }

    public function findActiveForBusiness(int $businessId): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM business_permissions WHERE business_id = :bid AND status = 'GRANTED' ORDER BY id DESC LIMIT 1",
            [':bid' => $businessId]
        );
    }

    public function recordPermission(array $data): int
    {
        return Database::insert(
            'INSERT INTO business_permissions (
                business_id, status, rep_full_name, rep_role_title, rep_phone, rep_email,
                permission_method, evidence_storage_path, scope_notes, collected_by_staff_id, granted_at
            ) VALUES (
                :business_id, :status, :rep_name, :rep_role, :rep_phone, :rep_email,
                :method, :evidence, :notes, :staff_id, :granted_at
            )',
            [
                ':business_id' => (int) $data['business_id'],
                ':status'      => (string) ($data['status'] ?? 'GRANTED'),
                ':rep_name'    => trim((string) $data['rep_full_name']),
                ':rep_role'    => trim((string) $data['rep_role_title']),
                ':rep_phone'   => trim((string) $data['rep_phone']),
                ':rep_email'   => !empty($data['rep_email']) ? trim((string) $data['rep_email']) : null,
                ':method'      => (string) ($data['permission_method'] ?? 'RECORDED_VISIT'),
                ':evidence'    => !empty($data['evidence_storage_path']) ? (string) $data['evidence_storage_path'] : null,
                ':notes'       => !empty($data['scope_notes']) ? trim((string) $data['scope_notes']) : null,
                ':staff_id'    => (int) $data['collected_by_staff_id'],
                ':granted_at'  => ($data['status'] ?? 'GRANTED') === 'GRANTED' ? date('Y-m-d H:i:s') : null,
            ]
        );
    }

    public function latestQcForBranch(int $branchId): ?array
    {
        return Database::fetchOne(
            'SELECT vr.*, s.full_name AS reviewer_name
             FROM verification_reviews vr
             LEFT JOIN staff_users s ON s.id = vr.reviewed_by_staff_id
             WHERE vr.branch_id = :bid
             ORDER BY vr.id DESC
             LIMIT 1',
            [':bid' => $branchId]
        );
    }

    public function recordQcReview(array $data): int
    {
        $id = Database::insert(
            'INSERT INTO verification_reviews (
                branch_id, permission_id, submitted_by_staff_id, reviewed_by_staff_id,
                outcome, checklist_json, reviewer_notes
            ) VALUES (
                :branch_id, :perm_id, :sub_staff, :rev_staff, :outcome, :checklist, :notes
            )',
            [
                ':branch_id' => (int) $data['branch_id'],
                ':perm_id'   => (int) $data['permission_id'],
                ':sub_staff' => (int) $data['submitted_by_staff_id'],
                ':rev_staff' => (int) $data['reviewed_by_staff_id'],
                ':outcome'   => (string) $data['outcome'],
                ':checklist' => is_string($data['checklist_json']) ? $data['checklist_json'] : json_encode($data['checklist_json']),
                ':notes'     => !empty($data['reviewer_notes']) ? trim((string) $data['reviewer_notes']) : null,
            ]
        );

        Cache::invalidateTags(['branches']);
        return $id;
    }
}
