<?php
declare(strict_types=1);

use Bulbula\Database\Migration;

return new class implements Migration {
    public function description(): string { return 'Record the audited Bulbula.et V1 baseline schema'; }

    public function up(\PDO $pdo, string $driver): void
    {
        $required = ['staff_users', 'categories', 'areas', 'businesses', 'branches', 'business_permissions', 'media_assets', 'customers', 'reviews', 'audit_logs'];
        foreach ($required as $table) {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t');
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=:t");
            }
            $stmt->execute([':t' => $table]);
            if ((int) $stmt->fetchColumn() !== 1) throw new \RuntimeException("Baseline cannot be recorded: required table {$table} is missing.");
        }
    }

    public function down(\PDO $pdo, string $driver): void
    {
        // Baseline rollback intentionally removes only its migration record.
        // Existing production V1 tables and data are never destructively dropped.
    }
};
