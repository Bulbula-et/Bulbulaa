<?php
declare(strict_types=1);

namespace Bulbula\Core;

use PDO;
use Throwable;

/** Production-only runtime integrity checks that require the live database. */
final class ProductionGuard
{
    /** @return string[] */
    public static function problems(): array
    {
        if ((string) Config::get('app.env', 'development') !== 'production') return [];
        try {
            $rows = Database::pdo()->query(
                'SELECT email, role, password_hash, totp_enabled, totp_secret_enc FROM staff_users WHERE is_active = 1'
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return ['Production staff credential readiness could not be verified.'];
        }
        if ($rows === []) return ['Production requires at least one active Staff administrator.'];
        $hasAdministrator = false;
        foreach ($rows as $row) $hasAdministrator = $hasAdministrator || ($row['role'] ?? '') === 'ADMINISTRATOR';
        $problems = $hasAdministrator ? [] : ['Production requires at least one active Staff administrator.'];
        foreach ($rows as $row) {
            $email = (string) ($row['email'] ?? 'unknown');
            $hash = (string) ($row['password_hash'] ?? '');
            if (self::isKnownPilotPassword($hash)) {
                $problems[] = "Active Staff account {$email} still uses a known pilot password.";
            }
            if (empty($row['totp_enabled']) || trim((string) ($row['totp_secret_enc'] ?? '')) === '') {
                $problems[] = "Active Staff account {$email} has not completed encrypted TOTP enrollment.";
            }
        }
        return $problems;
    }

    public static function isKnownPilotPassword(string $hash): bool
    {
        if ($hash === '') return true;
        foreach (['password', 'BulbulaStaff2026!'] as $known) {
            if (password_verify($known, $hash)) return true;
        }
        return false;
    }
}
