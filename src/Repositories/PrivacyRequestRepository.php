<?php
declare(strict_types=1);
namespace Bulbula\Repositories;
use Bulbula\Core\Database;
final class PrivacyRequestRepository
{
 public function listAll(int $limit=100): array { $limit=max(1,min(250,$limit)); return Database::fetchAll("SELECT pr.*,c.display_name FROM privacy_requests pr LEFT JOIN customers c ON c.id=pr.customer_id ORDER BY CASE pr.status WHEN 'SUBMITTED' THEN 0 WHEN 'IN_PROGRESS' THEN 1 ELSE 2 END,pr.created_at ASC LIMIT {$limit}"); }
 public function find(int $id): ?array { return Database::fetchOne('SELECT * FROM privacy_requests WHERE id=:id LIMIT 1',[':id'=>$id]); }
 public function resolve(int $id,int $staffId,string $status,string $summary): void
 {
  $pdo=Database::pdo();$pdo->beginTransaction();
  try{$row=$this->find($id);if($row===null)throw new \RuntimeException('Privacy request not found.');
   $completed=in_array($status,['COMPLETED','REJECTED_WITH_REASON'],true)?date('Y-m-d H:i:s'):null;
   Database::execute('UPDATE privacy_requests SET status=:status,handled_by_staff_id=:staff,resolution_summary=:summary,completed_at=:completed WHERE id=:id',[':status'=>$status,':staff'=>$staffId,':summary'=>$summary,':completed'=>$completed,':id'=>$id]);
   $customerId=(int)($row['customer_id']??0);
   if($customerId>0&&$row['request_type']==='ACCOUNT_DELETION'&&$status==='COMPLETED'){
    Database::execute("UPDATE customers SET email=:email,display_name='Deleted Customer',google_sub_id=NULL,password_hash=NULL,password_changed_at=NULL,phone_number=NULL,account_status='DELETED',updated_at=CURRENT_TIMESTAMP WHERE id=:id",[':email'=>'deleted+'. $customerId .'@privacy.invalid',':id'=>$customerId]);
    Database::execute("UPDATE account_sessions SET revoked_at=CURRENT_TIMESTAMP WHERE actor_type='CUSTOMER' AND actor_id=:id AND revoked_at IS NULL",[':id'=>$customerId]);
   } elseif($customerId>0&&$row['request_type']==='ACCOUNT_DELETION'&&$status==='REJECTED_WITH_REASON') Database::execute("UPDATE customers SET account_status='ACTIVE',updated_at=CURRENT_TIMESTAMP WHERE id=:id AND account_status='PENDING_DELETION'",[':id'=>$customerId]);
   $pdo->commit();
  }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
}
