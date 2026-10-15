<?php
declare(strict_types=1);
use Bulbula\Database\Migration;
return new class implements Migration {
 public function description(): string{return 'Add Customer self-service deletion grace period and exit reasons';}
 public function up(\PDO $pdo,string $driver): void {
  if($driver==='mysql'){$cols=[];foreach($pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='customers'")->fetchAll(\PDO::FETCH_COLUMN) as $c)$cols[]=$c;
   if(!in_array('deletion_requested_at',$cols,true))$pdo->exec("ALTER TABLE customers ADD COLUMN deletion_requested_at DATETIME NULL, ADD COLUMN deletion_effective_at DATETIME NULL, ADD COLUMN deletion_reason_code VARCHAR(40) NULL, ADD COLUMN deletion_reason_detail VARCHAR(1000) NULL, ADD INDEX idx_customer_deletion_due (account_status,deletion_effective_at)");return;}
  $cols=array_column($pdo->query('PRAGMA table_info(customers)')->fetchAll(\PDO::FETCH_ASSOC),'name');
  foreach(['deletion_requested_at'=>'DATETIME NULL','deletion_effective_at'=>'DATETIME NULL','deletion_reason_code'=>'TEXT NULL','deletion_reason_detail'=>'TEXT NULL'] as $name=>$type)if(!in_array($name,$cols,true))$pdo->exec("ALTER TABLE customers ADD COLUMN {$name} {$type}");
  $pdo->exec('CREATE INDEX IF NOT EXISTS idx_customer_deletion_due ON customers(account_status,deletion_effective_at)');
 }
 public function down(\PDO $pdo,string $driver): void { /* Retained to preserve deletion lifecycle evidence safely. */ }
};
