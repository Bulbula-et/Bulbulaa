<?php
declare(strict_types=1);
use Bulbula\Database\Migration;
return new class implements Migration {
 public function description(): string { return 'Register revocable Customer and Staff device sessions'; }
 public function up(\PDO $pdo,string $driver): void {
  if($driver==='mysql'){$pdo->exec("CREATE TABLE IF NOT EXISTS account_sessions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor_type VARCHAR(16) NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, device_label VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, revoked_at DATETIME NULL, INDEX idx_account_sessions_actor (actor_type,actor_id,revoked_at,last_seen_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");return;}
  $pdo->exec("CREATE TABLE IF NOT EXISTS account_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, actor_type TEXT NOT NULL, actor_id INTEGER NOT NULL, token_hash TEXT NOT NULL UNIQUE, device_label TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, revoked_at DATETIME NULL)");
  $pdo->exec('CREATE INDEX IF NOT EXISTS idx_account_sessions_actor ON account_sessions(actor_type,actor_id,revoked_at,last_seen_at)');
 }
 public function down(\PDO $pdo,string $driver): void { $pdo->exec('DROP TABLE IF EXISTS account_sessions'); }
};
