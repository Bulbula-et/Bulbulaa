<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Core\Cache;
use Bulbula\Core\Database;
use Bulbula\Repositories\BranchRepository;
use Bulbula\Repositories\MediaRepository;
use Bulbula\Repositories\PermissionRepository;
use Throwable;

final class MediaLifecycleService
{
    public function __construct(
        private readonly BranchRepository $branchRepo=new BranchRepository(),
        private readonly MediaRepository $mediaRepo=new MediaRepository(),
        private readonly PermissionRepository $permissionRepo=new PermissionRepository(),
        private readonly MediaUploadService $files=new MediaUploadService(),
        private readonly AuditService $audit=new AuditService()
    ) {}

    public function replaceCover(int $branchId,array $file,string $altText,int $staffId): array
    {
        $branch=$this->branchRepo->findByIdForStaff($branchId);
        if ($branch===null) return ['ok'=>false,'error'=>'Branch not found.'];
        $permission=$this->permissionRepo->findActiveForBusiness((int)$branch['business_id']);
        if ($permission===null || (int)($branch['active_permission_id']??0)!==(int)$permission['id']) return ['ok'=>false,'error'=>'Media replacement requires the Branch active granted permission.'];
        $altText=mb_substr(trim($altText),0,180,'UTF-8');
        if ($altText==='') return ['ok'=>false,'error'=>'Alternative text is required.'];
        $processed=$this->files->process($file,$branchId);
        if (!$processed['ok']) return $processed;
        $old=array_values(array_filter($this->mediaRepo->listActiveForBranch($branchId),static fn(array $m):bool=>(string)$m['media_type']==='COVER'));
        $tokens=$this->quarantineAll($old);
        if ($tokens===null) { $this->files->deleteProcessed((string)$processed['file_path']); return ['ok'=>false,'error'=>'Existing media could not be secured for replacement.']; }
        $pdo=Database::pdo();
        try {
            $pdo->beginTransaction();
            $this->mediaRepo->deactivateIds(array_column($old,'id'));
            $id=$this->mediaRepo->insertBranchCover(['business_id'=>$branch['business_id'],'branch_id'=>$branchId,'permission_id'=>$permission['id'],'file_path'=>$processed['file_path'],'width'=>$processed['width'],'height'=>$processed['height'],'size'=>$processed['size'],'alt_text_en'=>$altText,'staff_id'=>$staffId]);
            $this->audit->logStaffAction($staffId,'PERMITTED_MEDIA_REPLACED','media_asset',$id,['replaced_media_ids'=>array_map('intval',array_column($old,'id'))],['branch_id'=>$branchId,'permission_id'=>(int)$permission['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->restoreAll($tokens);$this->files->deleteProcessed((string)$processed['file_path']);
            return ['ok'=>false,'error'=>'Media replacement failed without changing the active cover.'];
        }
        $this->purgeAll($tokens);Cache::invalidateTags(['branches']);
        return ['ok'=>true,'media_id'=>$id];
    }

    public function remove(int $branchId,int $mediaId,string $reason,int $staffId): array
    {
        if (mb_strlen(trim($reason))<10) return ['ok'=>false,'error'=>'A removal reason of at least 10 characters is required.'];
        $asset=$this->mediaRepo->findActiveForBranch($mediaId,$branchId);
        if ($asset===null) return ['ok'=>false,'error'=>'Active Branch media was not found.'];
        $token=$this->files->quarantine((string)$asset['file_path']);
        if ($token===null) return ['ok'=>false,'error'=>'Media file could not be secured for removal.'];
        $pdo=Database::pdo();
        try {
            $pdo->beginTransaction();$this->mediaRepo->deactivateIds([$mediaId]);
            $this->audit->logStaffAction($staffId,'PERMITTED_MEDIA_REMOVED','media_asset',$mediaId,['is_active'=>1],['is_active'=>0,'branch_id'=>$branchId],trim($reason));
            $pdo->commit();
        } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();$this->files->restoreQuarantine($token);return ['ok'=>false,'error'=>'Media removal failed without changing the asset.']; }
        $this->files->purgeQuarantine($token);Cache::invalidateTags(['branches']);return ['ok'=>true];
    }

    public function revokePermission(int $branchId,string $reason,int $staffId): array
    {
        $reason=trim($reason);if(mb_strlen($reason)<10)return ['ok'=>false,'error'=>'A permission-withdrawal reason of at least 10 characters is required.'];
        $branch=$this->branchRepo->findByIdForStaff($branchId);if($branch===null)return ['ok'=>false,'error'=>'Branch not found.'];
        $permissionId=(int)($branch['active_permission_id']??0);$permission=$this->permissionRepo->findById($permissionId);
        if($permission===null || (string)$permission['status']!=='GRANTED' || (int)$permission['business_id']!==(int)$branch['business_id'])return ['ok'=>false,'error'=>'No active granted permission is attached to this Business.'];
        $assets=$this->mediaRepo->listActiveByPermission($permissionId);$tokens=$this->quarantineAll($assets);
        if($tokens===null)return ['ok'=>false,'error'=>'Permission withdrawal stopped because all affected media could not be secured.'];
        $pdo=Database::pdo();
        try {
            $pdo->beginTransaction();
            Database::execute("UPDATE business_permissions SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP,revocation_reason=:reason WHERE id=:id AND status='GRANTED'",[':reason'=>$reason,':id'=>$permissionId]);
            Database::execute("UPDATE branches SET active_permission_id=NULL,publication_status='UNPUBLISHED',is_verified=0,last_verified_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE business_id=:business",[':business'=>(int)$branch['business_id']]);
            Database::execute("UPDATE businesses SET lifecycle_status='UNPUBLISHED',updated_at=CURRENT_TIMESTAMP WHERE id=:business",[':business'=>(int)$branch['business_id']]);
            $this->mediaRepo->deactivateIds(array_column($assets,'id'));
            $this->audit->logStaffAction($staffId,'BUSINESS_PERMISSION_REVOKED','business_permission',$permissionId,['status'=>'GRANTED'],['status'=>'REVOKED','business_id'=>(int)$branch['business_id'],'deactivated_media_ids'=>array_map('intval',array_column($assets,'id'))],$reason);
            $pdo->commit();
        } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$this->restoreAll($tokens);return ['ok'=>false,'error'=>'Permission withdrawal failed without changing publication or media.'];}
        $this->purgeAll($tokens);Cache::invalidateTags(['branches','businesses']);return ['ok'=>true,'media_removed'=>count($assets)];
    }

    private function quarantineAll(array $assets): ?array
    {
        $tokens=[];foreach($assets as $asset){$token=$this->files->quarantine((string)$asset['file_path']);if($token===null){$this->restoreAll($tokens);return null;}$tokens[]=$token;}return $tokens;
    }
    private function restoreAll(array $tokens): void {foreach(array_reverse($tokens) as $token)$this->files->restoreQuarantine($token);}
    private function purgeAll(array $tokens): void {foreach($tokens as $token)$this->files->purgeQuarantine($token);}
}
