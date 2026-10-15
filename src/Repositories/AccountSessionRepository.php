<?php
declare(strict_types=1);
namespace Bulbula\Repositories;
use Bulbula\Core\Database;
final class AccountSessionRepository {
 public function find(string $hash): ?array { return Database::fetchOne('SELECT * FROM account_sessions WHERE token_hash=:hash LIMIT 1',[':hash'=>$hash]); }
 public function create(string $type,int $actorId,string $hash,string $label): void { Database::execute('INSERT INTO account_sessions(actor_type,actor_id,token_hash,device_label) VALUES(:type,:actor,:hash,:label)',[':type'=>$type,':actor'=>$actorId,':hash'=>$hash,':label'=>$label]); }
 public function touch(string $hash): void { Database::execute('UPDATE account_sessions SET last_seen_at=CURRENT_TIMESTAMP WHERE token_hash=:hash AND revoked_at IS NULL AND last_seen_at < :cutoff',[':hash'=>$hash,':cutoff'=>date('Y-m-d H:i:s',time()-300)]); }
 public function listActive(string $type,int $actorId): array { return Database::fetchAll('SELECT id,token_hash,device_label,created_at,last_seen_at FROM account_sessions WHERE actor_type=:type AND actor_id=:actor AND revoked_at IS NULL ORDER BY last_seen_at DESC',[':type'=>$type,':actor'=>$actorId]); }
 public function revoke(int $id,string $type,int $actorId,string $currentHash): void { Database::execute('UPDATE account_sessions SET revoked_at=CURRENT_TIMESTAMP WHERE id=:id AND actor_type=:type AND actor_id=:actor AND token_hash<>:current',[':id'=>$id,':type'=>$type,':actor'=>$actorId,':current'=>$currentHash]); }
 public function revokeOthers(string $type,int $actorId,string $currentHash): void { Database::execute('UPDATE account_sessions SET revoked_at=CURRENT_TIMESTAMP WHERE actor_type=:type AND actor_id=:actor AND token_hash<>:hash AND revoked_at IS NULL',[':type'=>$type,':actor'=>$actorId,':hash'=>$currentHash]); }
 public function revokeHash(string $hash): void { Database::execute('UPDATE account_sessions SET revoked_at=CURRENT_TIMESTAMP WHERE token_hash=:hash AND revoked_at IS NULL',[':hash'=>$hash]); }
}
