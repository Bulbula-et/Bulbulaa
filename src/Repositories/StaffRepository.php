<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Database;

/**
 * Staff Console Accounts Repository (C-19..C-29, Gate D-45).
 */
final class StaffRepository
{
    public function findById(int $id): ?array
    {
        return Database::fetchOne(
            'SELECT id, email, full_name, role, totp_enabled, is_active, last_login_at
             FROM staff_users
             WHERE id = :id AND is_active = 1
             LIMIT 1',
            [':id' => $id]
        );
    }

    public function findByEmailForAuth(string $email): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM staff_users WHERE LOWER(email) = :email AND is_active = 1 LIMIT 1',
            [':email' => mb_strtolower(trim($email), 'UTF-8')]
        );
    }

    public function recordLoginTimestamp(int $staffId): void
    {
        Database::execute(
            'UPDATE staff_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id',
            [':id' => $staffId]
        );
    }

    public function listActiveStaff(): array
    {
        return Database::fetchAll(
            'SELECT id, email, full_name, role, totp_enabled, last_login_at, created_at
             FROM staff_users
             WHERE is_active = 1
             ORDER BY role DESC, full_name ASC'
        );
    }
}
