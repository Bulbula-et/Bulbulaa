<?php
declare(strict_types=1);
use Bulbula\Database\Migration;
return new class implements Migration {
 public function description(): string{return 'Add immutable Customer review appeals and Administrator decisions';}
 public function up(\PDO $pdo,string $driver): void {
  $id=$driver==='mysql'?'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
  $big=$driver==='mysql'?'BIGINT UNSIGNED':'INTEGER';
  $text=$driver==='mysql'?'VARCHAR(2000)':'TEXT';
  $short=$driver==='mysql'?'VARCHAR(32)':'TEXT';
  $pdo->exec("CREATE TABLE IF NOT EXISTS review_appeals (id {$id},review_id {$big} NOT NULL UNIQUE,customer_id {$big} NOT NULL,original_status {$short} NOT NULL,original_reason_code VARCHAR(64) NULL,original_moderated_by_staff_id {$big} NULL,original_moderated_at DATETIME NOT NULL,appeal_statement {$text} NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(review_id) REFERENCES reviews(id),FOREIGN KEY(customer_id) REFERENCES customers(id),FOREIGN KEY(original_moderated_by_staff_id) REFERENCES staff_users(id))");
  $pdo->exec("CREATE TABLE IF NOT EXISTS review_appeal_decisions (id {$id},appeal_id {$big} NOT NULL UNIQUE,decided_by_admin_id {$big} NOT NULL,outcome {$short} NOT NULL,reason_code VARCHAR(64) NOT NULL,decision_notes {$text} NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(appeal_id) REFERENCES review_appeals(id),FOREIGN KEY(decided_by_admin_id) REFERENCES staff_users(id))");
  if($driver==='mysql'){$exists=$pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='review_appeals' AND index_name='idx_review_appeals_created'")->fetchColumn();if((int)$exists===0)$pdo->exec('CREATE INDEX idx_review_appeals_created ON review_appeals(created_at)');}else{$pdo->exec('CREATE INDEX IF NOT EXISTS idx_review_appeals_created ON review_appeals(created_at)');}
 }
 public function down(\PDO $pdo,string $driver): void {$pdo->exec('DROP TABLE IF EXISTS review_appeal_decisions');$pdo->exec('DROP TABLE IF EXISTS review_appeals');}
};
