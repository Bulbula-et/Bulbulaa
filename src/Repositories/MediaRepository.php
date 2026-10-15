<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Database;

final class MediaRepository
{
    public function listActiveForBranch(int $branchId): array
    {
        return Database::fetchAll('SELECT * FROM media_assets WHERE branch_id=:branch AND is_active=1 ORDER BY display_order,id', [':branch'=>$branchId]);
    }

    public function findActiveForBranch(int $mediaId, int $branchId): ?array
    {
        return Database::fetchOne('SELECT * FROM media_assets WHERE id=:id AND branch_id=:branch AND is_active=1 LIMIT 1', [':id'=>$mediaId,':branch'=>$branchId]);
    }

    public function listActiveByPermission(int $permissionId): array
    {
        return Database::fetchAll('SELECT * FROM media_assets WHERE permission_id=:permission AND is_active=1 ORDER BY id', [':permission'=>$permissionId]);
    }

    public function deactivateIds(array $ids): void
    {
        $ids=array_values(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0));
        if ($ids===[]) return;
        Database::execute('UPDATE media_assets SET is_active=0 WHERE id IN ('.implode(',',$ids).')');
    }

    public function insertBranchCover(array $data): int
    {
        return Database::insert(
            "INSERT INTO media_assets (business_id,branch_id,permission_id,media_type,file_path,width_px,height_px,file_size_bytes,alt_text_en,display_order,is_active,uploaded_by_staff_id)
             VALUES (:business,:branch,:permission,'COVER',:path,:width,:height,:bytes,:alt,1,1,:staff)",
            [':business'=>(int)$data['business_id'],':branch'=>(int)$data['branch_id'],':permission'=>(int)$data['permission_id'],':path'=>(string)$data['file_path'],':width'=>(int)$data['width'],':height'=>(int)$data['height'],':bytes'=>(int)$data['size'],':alt'=>(string)$data['alt_text_en'],':staff'=>(int)$data['staff_id']]
        );
    }
}
