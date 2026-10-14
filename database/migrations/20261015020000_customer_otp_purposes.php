<?php
declare(strict_types=1);

use Bulbula\Database\Migration;

return new class implements Migration {
    public function description(): string { return 'Scope Customer email codes to sign-in, verification, or password recovery'; }

    public function up(\PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer_otp_tokens' AND column_name = 'purpose'");
            if ((int) $stmt->fetchColumn() === 0) {
                $pdo->exec("ALTER TABLE customer_otp_tokens ADD COLUMN purpose VARCHAR(24) NOT NULL DEFAULT 'SIGN_IN' AFTER email, ADD INDEX idx_otp_email_purpose_expires (email, purpose, expires_at)");
            }
            return;
        }
        $columns = array_column($pdo->query('PRAGMA table_info(customer_otp_tokens)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
        if (!in_array('purpose', $columns, true)) $pdo->exec("ALTER TABLE customer_otp_tokens ADD COLUMN purpose TEXT NOT NULL DEFAULT 'SIGN_IN'");
    }

    public function down(\PDO $pdo, string $driver): void
    {
        // Purpose scoping is security-relevant and intentionally retained.
    }
};
