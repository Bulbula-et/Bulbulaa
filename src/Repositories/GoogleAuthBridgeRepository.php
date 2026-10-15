<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Database;

/** Single-use browser-to-Mini-App Google handoff; no Google token or email is stored. */
final class GoogleAuthBridgeRepository
{
    public function create(string $flowHash, string $sourceSecretHash, string $expiresAt): void
    {
        Database::execute('INSERT INTO google_auth_bridges (flow_hash, source_secret_hash, expires_at) VALUES (:flow, :source, :expires)', [
            ':flow' => $flowHash, ':source' => $sourceSecretHash, ':expires' => $expiresAt,
        ]);
    }

    public function available(string $flowHash, string $now): bool
    {
        return Database::fetchOne('SELECT flow_hash FROM google_auth_bridges WHERE flow_hash = :flow AND expires_at > :now AND completed_at IS NULL AND consumed_at IS NULL', [
            ':flow' => $flowHash, ':now' => $now,
        ]) !== null;
    }

    public function complete(string $flowHash, int $customerId, string $now): bool
    {
        return Database::execute('UPDATE google_auth_bridges SET customer_id = :customer, completed_at = :now_set WHERE flow_hash = :flow AND expires_at > :now_check AND completed_at IS NULL AND consumed_at IS NULL', [
            ':customer' => $customerId, ':now_set' => $now, ':flow' => $flowHash, ':now_check' => $now,
        ]) === 1;
    }

    /** Atomically grants at most one session-bound consumption. */
    public function consume(string $flowHash, string $sourceSecretHash, string $now): ?int
    {
        $changed = Database::execute('UPDATE google_auth_bridges SET consumed_at = :now_set WHERE flow_hash = :flow AND source_secret_hash = :source AND expires_at > :now_check AND customer_id IS NOT NULL AND completed_at IS NOT NULL AND consumed_at IS NULL', [
            ':now_set' => $now, ':flow' => $flowHash, ':source' => $sourceSecretHash, ':now_check' => $now,
        ]);
        if ($changed !== 1) return null;
        $row = Database::fetchOne('SELECT customer_id FROM google_auth_bridges WHERE flow_hash = :flow', [':flow' => $flowHash]);
        return $row !== null ? (int) $row['customer_id'] : null;
    }

    public function status(string $flowHash, string $sourceSecretHash, string $now): string
    {
        $row = Database::fetchOne('SELECT customer_id, consumed_at, expires_at FROM google_auth_bridges WHERE flow_hash = :flow AND source_secret_hash = :source', [
            ':flow' => $flowHash, ':source' => $sourceSecretHash,
        ]);
        if ($row === null || $row['consumed_at'] !== null || (string) $row['expires_at'] <= $now) return 'expired';
        return $row['customer_id'] !== null ? 'ready' : 'pending';
    }

    public function purgeOld(string $cutoff): void
    {
        Database::execute('DELETE FROM google_auth_bridges WHERE expires_at < :cutoff', [':cutoff' => $cutoff]);
    }
}
