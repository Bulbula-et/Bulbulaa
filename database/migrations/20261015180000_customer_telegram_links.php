<?php
declare(strict_types=1);
use Bulbula\Database\Migration;

return new class implements Migration {
    public function description(): string { return 'One-to-one verified Telegram Mini App identity links for Customers'; }

    public function up(\PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS customer_telegram_links (
                telegram_identity_hash CHAR(64) NOT NULL PRIMARY KEY,
                customer_id BIGINT UNSIGNED NOT NULL UNIQUE,
                linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_telegram_link_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS customer_telegram_links (
            telegram_identity_hash TEXT NOT NULL PRIMARY KEY,
            customer_id INTEGER NOT NULL UNIQUE REFERENCES customers(id) ON DELETE CASCADE,
            linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(\PDO $pdo, string $driver): void
    {
        $pdo->exec('DROP TABLE IF EXISTS customer_telegram_links');
    }
};
