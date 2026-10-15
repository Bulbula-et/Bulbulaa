<?php
declare(strict_types=1);
use Bulbula\Database\Migration;

return new class implements Migration {
    public function description(): string { return 'Versioned Administrator-managed transactional email designs'; }
    public function up(\PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS email_template_revisions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_key VARCHAR(80) NOT NULL,
                version INT UNSIGNED NOT NULL,
                subject_template VARCHAR(200) NOT NULL,
                html_template MEDIUMTEXT NOT NULL,
                text_template TEXT NOT NULL,
                project_json MEDIUMTEXT NOT NULL,
                created_by BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_email_template_revision (template_key,version),
                INDEX idx_email_template_key (template_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS email_template_active (template_key VARCHAR(80) PRIMARY KEY, revision_id BIGINT UNSIGNED NOT NULL, CONSTRAINT fk_email_template_revision FOREIGN KEY (revision_id) REFERENCES email_template_revisions(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            return;
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS email_template_revisions (id INTEGER PRIMARY KEY AUTOINCREMENT, template_key TEXT NOT NULL, version INTEGER NOT NULL, subject_template TEXT NOT NULL, html_template TEXT NOT NULL, text_template TEXT NOT NULL, project_json TEXT NOT NULL, created_by INTEGER NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(template_key,version))');
        $pdo->exec('CREATE TABLE IF NOT EXISTS email_template_active (template_key TEXT PRIMARY KEY, revision_id INTEGER NOT NULL REFERENCES email_template_revisions(id))');
    }
    public function down(\PDO $pdo, string $driver): void
    {
        $pdo->exec('DROP TABLE IF EXISTS email_template_active');
        $pdo->exec('DROP TABLE IF EXISTS email_template_revisions');
    }
};
