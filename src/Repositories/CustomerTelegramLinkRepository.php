<?php
declare(strict_types=1);
namespace Bulbula\Repositories;

use Bulbula\Core\Config;
use Bulbula\Core\Database;
use PDOException;

final class CustomerTelegramLinkRepository
{
    private function identityHash(string $telegramId): string
    {
        $secret = (string) Config::get('app.secret_key', '');
        if (strlen($secret) < 32) throw new \RuntimeException('A strong app secret is required for Telegram links.');
        return hash_hmac('sha256', 'telegram-user:' . $telegramId, $secret);
    }

    public function customerIdFor(string $telegramId): ?int
    {
        $row = Database::fetchOne('SELECT customer_id FROM customer_telegram_links WHERE telegram_identity_hash=:hash', [':hash'=>$this->identityHash($telegramId)]);
        return $row === null ? null : (int) $row['customer_id'];
    }

    public function isLinked(int $customerId): bool
    {
        return Database::fetchOne('SELECT customer_id FROM customer_telegram_links WHERE customer_id=:id', [':id'=>$customerId]) !== null;
    }

    /** Unique constraints enforce both sides of the one-to-one relationship even under concurrent requests. */
    public function link(string $telegramId, int $customerId): bool
    {
        if ($this->customerIdFor($telegramId) === $customerId) return true;
        try {
            Database::execute('INSERT INTO customer_telegram_links (telegram_identity_hash,customer_id) VALUES (:hash,:id)', [':hash'=>$this->identityHash($telegramId),':id'=>$customerId]);
            return true;
        } catch (PDOException $e) {
            if (($e->errorInfo[0] ?? '') === '23000') return false;
            throw $e;
        }
    }

    public function unlinkForCustomer(int $customerId): bool
    {
        return Database::execute('DELETE FROM customer_telegram_links WHERE customer_id=:id', [':id'=>$customerId]) === 1;
    }

    public function unlink(string $telegramId, int $customerId): bool
    {
        return Database::execute('DELETE FROM customer_telegram_links WHERE telegram_identity_hash=:hash AND customer_id=:id', [':hash'=>$this->identityHash($telegramId), ':id'=>$customerId]) === 1;
    }
}
