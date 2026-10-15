<?php
declare(strict_types=1);
use Bulbula\Database\Migration;

return new class implements Migration {
    public function description(): string { return 'Short-lived, session-bound Google browser handoff for Telegram Mini Apps'; }

    public function up(\PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS google_auth_bridges (
                flow_hash CHAR(64) NOT NULL PRIMARY KEY,
                source_secret_hash CHAR(64) NOT NULL,
                customer_id BIGINT UNSIGNED NULL,
                expires_at DATETIME NOT NULL,
                completed_at DATETIME NULL,
                consumed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_google_bridge_expiry (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS google_auth_bridges (
            flow_hash TEXT NOT NULL PRIMARY KEY,
            source_secret_hash TEXT NOT NULL,
            customer_id INTEGER NULL,
            expires_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            consumed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_google_bridge_expiry ON google_auth_bridges(expires_at)');
    }

    public function down(\PDO $pdo, string $driver): void
    {
        $pdo->exec('DROP TABLE IF EXISTS google_auth_bridges');
    }
};
