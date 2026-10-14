<?php
declare(strict_types=1);

use Bulbula\Database\Migration;

return new class implements Migration {
    public function description(): string { return 'Add Customer password authentication and personal profile fields'; }

    public function up(\PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $columns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customers'")->fetchAll(\PDO::FETCH_COLUMN);
            $add = [];
            if (!in_array('password_hash', $columns, true)) $add[] = 'ADD COLUMN password_hash VARCHAR(255) NULL AFTER google_sub_id';
            if (!in_array('phone_number', $columns, true)) $add[] = 'ADD COLUMN phone_number VARCHAR(32) NULL AFTER display_name';
            if (!in_array('preferred_language', $columns, true)) $add[] = "ADD COLUMN preferred_language VARCHAR(8) NOT NULL DEFAULT 'en' AFTER phone_number";
            if (!in_array('password_changed_at', $columns, true)) $add[] = 'ADD COLUMN password_changed_at DATETIME NULL AFTER email_verified_at';
            if ($add !== []) $pdo->exec('ALTER TABLE customers ' . implode(', ', $add));
            return;
        }

        $columns = array_column($pdo->query("PRAGMA table_info(customers)")->fetchAll(\PDO::FETCH_ASSOC), 'name');
        if (!in_array('password_hash', $columns, true)) $pdo->exec('ALTER TABLE customers ADD COLUMN password_hash TEXT NULL');
        if (!in_array('phone_number', $columns, true)) $pdo->exec('ALTER TABLE customers ADD COLUMN phone_number TEXT NULL');
        if (!in_array('preferred_language', $columns, true)) $pdo->exec("ALTER TABLE customers ADD COLUMN preferred_language TEXT NOT NULL DEFAULT 'en'");
        if (!in_array('password_changed_at', $columns, true)) $pdo->exec('ALTER TABLE customers ADD COLUMN password_changed_at DATETIME NULL');
    }

    public function down(\PDO $pdo, string $driver): void
    {
        // Additive identity migration is intentionally non-destructive on rollback.
    }
};
