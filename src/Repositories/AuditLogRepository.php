<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Database;

/**
 * Append-Only Audit Log Repository (C-29).
 *
 * V1 Security & Privacy Rules (Page 8):
 * - Record who performed a relevant action and what happened.
 * - Restrict audit-log access to Administrators.
 * - NEVER place credentials, OTP codes, session identifiers, or unnecessary personal data in logs.
 * - Strictly append-only: no UPDATE or DELETE methods exist on this repository.
 */
final class AuditLogRepository
{
    private const FORBIDDEN_KEYS = [
        'password',
        'password_hash',
        'totp_secret',
        'totp_secret_enc',
        'otp',
        'otp_code',
        'code_hmac_sha256',
        'session_id',
        '_csrf',
        'token',
    ];

    public function append(
        string $actorType,
        ?int $actorStaffId,
        ?int $actorCustomerId,
        string $actionCode,
        string $entityType,
        int $entityId,
        ?array $beforeState = null,
        ?array $afterState = null,
        ?string $reasonNotes = null
    ): int {
        $cleanBefore = $beforeState !== null ? json_encode($this->scrubSensitiveKeys($beforeState), JSON_UNESCAPED_UNICODE) : null;
        $cleanAfter  = $afterState !== null ? json_encode($this->scrubSensitiveKeys($afterState), JSON_UNESCAPED_UNICODE) : null;

        return Database::insert(
            'INSERT INTO audit_logs (
                actor_type, actor_staff_id, actor_customer_id,
                action_code, entity_type, entity_id,
                before_state_json, after_state_json, reason_notes
            ) VALUES (
                :actor_type, :staff_id, :cust_id,
                :action, :etype, :eid,
                :before_json, :after_json, :reason
            )',
            [
                ':actor_type'  => $actorType,
                ':staff_id'    => $actorStaffId,
                ':cust_id'     => $actorCustomerId,
                ':action'      => $actionCode,
                ':etype'       => $entityType,
                ':eid'         => $entityId,
                ':before_json' => $cleanBefore,
                ':after_json'  => $cleanAfter,
                ':reason'      => $reasonNotes,
            ]
        );
    }

    public function listForAdministrator(int $limit = 100): array
    {
        $safeLimit = max(1, min(250, $limit));
        return Database::fetchAll(
            "SELECT al.*,
                    s.full_name AS staff_name, s.role AS staff_role
             FROM audit_logs al
             LEFT JOIN staff_users s ON s.id = al.actor_staff_id
             ORDER BY al.id DESC
             LIMIT {$safeLimit}"
        );
    }

    private function scrubSensitiveKeys(array $payload): array
    {
        $scrubbed = [];
        foreach ($payload as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if (in_array($lowerKey, self::FORBIDDEN_KEYS, true)) {
                $scrubbed[$key] = '[REDACTED_BY_C29_POLICY]';
                continue;
            }
            if (is_array($value)) {
                $scrubbed[$key] = $this->scrubSensitiveKeys($value);
            } else {
                $scrubbed[$key] = $value;
            }
        }
        return $scrubbed;
    }
}
