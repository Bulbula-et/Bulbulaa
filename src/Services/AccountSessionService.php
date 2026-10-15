<?php
declare(strict_types=1);
namespace Bulbula\Services;
use Bulbula\Core\Session;
use Bulbula\Repositories\AccountSessionRepository;
final class AccountSessionService {
 public function __construct(private readonly AccountSessionRepository $repo=new AccountSessionRepository()){}
 public function begin(string $type,int $actorId): void { Session::forget('_account_session_token'); $this->ensure($type,$actorId); }
 public function ensure(string $type,int $actorId): bool {
  $token=Session::get('_account_session_token');
  if(!is_string($token)||strlen($token)<32){$token=bin2hex(random_bytes(32));Session::set('_account_session_token',$token);}
  $hash=hash('sha256',$token);$row=$this->repo->find($hash);
  if($row===null){$this->repo->create($type,$actorId,$hash,$this->deviceLabel());return true;}
  if($row['actor_type']!==$type||(int)$row['actor_id']!==$actorId||!empty($row['revoked_at'])) return false;
  $this->repo->touch($hash);return true;
 }
 public function currentHash(): string { $token=(string)Session::get('_account_session_token','');return $token===''?'':hash('sha256',$token); }
 public function list(string $type,int $actorId): array { $current=$this->currentHash();return array_map(static function(array $row)use($current){$row['is_current']=hash_equals($current,(string)$row['token_hash']);unset($row['token_hash']);return $row;},$this->repo->listActive($type,$actorId)); }
 public function revoke(int $id,string $type,int $actorId): void {$this->repo->revoke($id,$type,$actorId,$this->currentHash());}
 public function revokeOthers(string $type,int $actorId): void {$this->repo->revokeOthers($type,$actorId,$this->currentHash());}
 public function revokeCurrent(): void {$hash=$this->currentHash();if($hash!=='')$this->repo->revokeHash($hash);}
 private function deviceLabel(): string {$ua=(string)($_SERVER['HTTP_USER_AGENT']??'Unknown browser');$browser=str_contains($ua,'Edg/')?'Edge':(str_contains($ua,'Chrome/')?'Chrome':(str_contains($ua,'Firefox/')?'Firefox':(str_contains($ua,'Safari/')?'Safari':'Browser')));$os=str_contains($ua,'Android')?'Android':(str_contains($ua,'iPhone')||str_contains($ua,'iPad')?'iOS':(str_contains($ua,'Windows')?'Windows':(str_contains($ua,'Macintosh')?'macOS':(str_contains($ua,'Linux')?'Linux':'device'))));return $browser.' on '.$os;}
}
