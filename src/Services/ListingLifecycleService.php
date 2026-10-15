<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Core\Cache;
use Bulbula\Core\Database;
use Bulbula\Domain\Policies\ListingLifecyclePolicy;
use Bulbula\Repositories\BranchRepository;
use Throwable;

final class ListingLifecycleService
{
    public function __construct(
        private readonly BranchRepository $branches = new BranchRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {}

    /** @return array{ok:bool,error?:string} */
    public function transition(int $branchId, string $target, string $reason, int $staffId): array
    {
        $branch = $this->branches->findByIdForStaff($branchId);
        if ($branch === null) return ['ok'=>false, 'error'=>'Branch not found.'];
        $from = (string)$branch['publication_status'];
        $target = strtoupper(trim($target));
        $reason = trim($reason);
        $evaluation = ListingLifecyclePolicy::evaluate($from, $target, $reason);
        if (!$evaluation['allowed']) return ['ok'=>false, 'error'=>(string)$evaluation['error']];

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            $verified = $target === 'TEMPORARILY_CLOSED' ? (int)$branch['is_verified'] : 0;
            $stmt = $pdo->prepare('UPDATE branches SET publication_status=:status,is_verified=:verified,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND publication_status=:from');
            $stmt->execute([':status'=>$target, ':verified'=>$verified, ':id'=>$branchId, ':from'=>$from]);
            if ($stmt->rowCount() !== 1) throw new \RuntimeException('The listing changed before this action completed.');

            $businessId = (int)$branch['business_id'];
            $publicCount = $pdo->prepare("SELECT COUNT(*) FROM branches WHERE business_id=:id AND publication_status IN ('PUBLISHED','TEMPORARILY_CLOSED')");
            $publicCount->execute([':id'=>$businessId]);
            $hasPublicBranch = (int)$publicCount->fetchColumn() > 0;
            $businessStatus = $hasPublicBranch ? 'PUBLISHED' : match ($target) {
                'PERMANENTLY_CLOSED' => 'CLOSED',
                'UNPUBLISHED' => 'UNPUBLISHED',
                default => 'DRAFT',
            };
            $pdo->prepare('UPDATE businesses SET lifecycle_status=:status,updated_by_staff_id=:staff,updated_at=CURRENT_TIMESTAMP WHERE id=:id')
                ->execute([':status'=>$businessStatus, ':staff'=>$staffId, ':id'=>$businessId]);
            $this->audit->logStaffAction($staffId, 'BRANCH_LIFECYCLE_'.$target, 'branch', $branchId, ['publication_status'=>$from, 'is_verified'=>$branch['is_verified']], ['publication_status'=>$target, 'is_verified'=>$verified], $reason);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Listing lifecycle transition failed: '.$e->getMessage());
            return ['ok'=>false, 'error'=>'The listing changed or could not be updated. Refresh and try again.'];
        }

        try { Cache::invalidateTags(['branches','businesses','categories','areas','campaigns']); } catch (Throwable $e) { error_log('Listing lifecycle cache invalidation failed: '.$e->getMessage()); }
        return ['ok'=>true];
    }

    /** @return array{ok:bool,error?:string,reverification_required?:bool} */
    public function updateVerifiedDetails(int $branchId, array $input, int $staffId): array
    {
        $branch = $this->branches->findByIdForStaff($branchId);
        if ($branch === null) return ['ok'=>false, 'error'=>'Branch not found.'];

        $businessName = mb_substr(trim((string)($input['business_name_en'] ?? $branch['business_name_en'])), 0, 180);
        $summary = mb_substr(trim((string)($input['brand_summary_en'] ?? ($branch['brand_summary_en'] ?? ''))), 0, 500) ?: null;
        $address = mb_substr(trim((string)($input['address_line_en'] ?? $branch['address_line_en'])), 0, 300);
        $phone = mb_substr(trim((string)($input['phone_primary'] ?? ($branch['phone_primary'] ?? ''))), 0, 40) ?: null;
        $hours = strtoupper((string)($input['opening_hours_status'] ?? $branch['opening_hours_status']));
        if ($businessName === '' || mb_strlen($address) < 5) return ['ok'=>false, 'error'=>'Business name and a complete physical address are required.'];
        if (!in_array($hours, ['KNOWN','UNKNOWN','BY_APPOINTMENT'], true)) return ['ok'=>false, 'error'=>'Select a supported opening-hours status.'];

        $before = ['business_name_en'=>$branch['business_name_en'], 'brand_summary_en'=>$branch['brand_summary_en'], 'address_line_en'=>$branch['address_line_en'], 'phone_primary'=>$branch['phone_primary'], 'opening_hours_status'=>$branch['opening_hours_status'], 'publication_status'=>$branch['publication_status']];
        $after = ['business_name_en'=>$businessName, 'brand_summary_en'=>$summary, 'address_line_en'=>$address, 'phone_primary'=>$phone, 'opening_hours_status'=>$hours];
        $changed = array_filter($after, static fn(mixed $value, string $key): bool => $value !== ($before[$key] ?? null), ARRAY_FILTER_USE_BOTH);
        if ($changed === []) return ['ok'=>false, 'error'=>'No listing details changed.'];

        $businessChanged = $businessName !== (string)$branch['business_name_en'] || $summary !== ($branch['brand_summary_en'] ?? null);
        $requiresQc = in_array((string)$branch['publication_status'], ['PUBLISHED','TEMPORARILY_CLOSED'], true);
        $newStatus = $requiresQc ? 'PENDING_QC' : (string)$branch['publication_status'];
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE businesses SET name_en=:name,brand_summary_en=:summary,updated_by_staff_id=:staff,updated_at=CURRENT_TIMESTAMP WHERE id=:id')
                ->execute([':name'=>$businessName, ':summary'=>$summary, ':staff'=>$staffId, ':id'=>(int)$branch['business_id']]);
            $pdo->prepare('UPDATE branches SET address_line_en=:address,phone_primary=:phone,opening_hours_status=:hours,publication_status=:status,is_verified=:verified,updated_at=CURRENT_TIMESTAMP WHERE id=:id')
                ->execute([':address'=>$address, ':phone'=>$phone, ':hours'=>$hours, ':status'=>$newStatus, ':verified'=>$requiresQc?0:(int)$branch['is_verified'], ':id'=>$branchId]);
            if ($businessChanged) {
                $pdo->prepare("UPDATE branches SET publication_status='PENDING_QC',is_verified=0,updated_at=CURRENT_TIMESTAMP WHERE business_id=:business AND publication_status IN ('PUBLISHED','TEMPORARILY_CLOSED')")
                    ->execute([':business'=>(int)$branch['business_id']]);
                $requiresQc = true;
            }
            if ($requiresQc) {
                $public = $pdo->prepare("SELECT COUNT(*) FROM branches WHERE business_id=:id AND publication_status IN ('PUBLISHED','TEMPORARILY_CLOSED')");
                $public->execute([':id'=>(int)$branch['business_id']]);
                $businessStatus = (int)$public->fetchColumn() > 0 ? 'PUBLISHED' : 'DRAFT';
                $pdo->prepare('UPDATE businesses SET lifecycle_status=:status WHERE id=:id')->execute([':status'=>$businessStatus, ':id'=>(int)$branch['business_id']]);
            }
            $after['publication_status']=$newStatus;
            $this->audit->logStaffAction($staffId, 'LISTING_DETAILS_CORRECTED', 'branch', $branchId, $before, $after, $requiresQc?'Published listing returned to QC after correction.':'Draft listing corrected.');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Listing correction failed: '.$e->getMessage());
            return ['ok'=>false, 'error'=>'The correction could not be saved. No partial change was retained.'];
        }

        try { Cache::invalidateTags(['branches','businesses','categories','areas','campaigns']); } catch (Throwable $e) { error_log('Listing correction cache invalidation failed: '.$e->getMessage()); }
        return ['ok'=>true, 'reverification_required'=>$requiresQc];
    }
}
