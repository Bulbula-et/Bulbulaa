<?php
declare(strict_types=1);
namespace Bulbula\Services;

/** Customer-only policy. Staff credentials retain their independent rules. */
final class CustomerPasswordPolicy
{
    private const ARGON_OPTIONS = ['memory_cost'=>19456, 'time_cost'=>2, 'threads'=>1];
    private const TRIVIAL = [
        '12345678', '123456789', '1234567890', '87654321', '00000000', '11111111',
        'password', 'password1', 'password12', 'password123', 'qwerty123', 'qwertyui',
        'admin123', 'letmein1', 'abcdefgh', 'asdfghjk',
    ];

    public function error(string $password): ?string
    {
        if (!mb_check_encoding($password, 'UTF-8') || str_contains($password, "\0")) return 'Use text characters for your password.';
        $length = mb_strlen($password, 'UTF-8');
        if ($length < 8) return 'Use at least 8 characters for your password.';
        if ($length > 128) return 'Use no more than 128 characters for your password.';
        if (in_array(mb_strtolower($password, 'UTF-8'), self::TRIVIAL, true)) return 'This password is very common. Choose a different one.';
        return null;
    }

    public function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID, self::ARGON_OPTIONS);
        if (!is_string($hash)) throw new \RuntimeException('Password hashing failed.');
        return $hash;
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::ARGON_OPTIONS);
    }
}
