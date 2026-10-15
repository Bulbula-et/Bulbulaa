<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Core\Cache;
use Bulbula\Core\Database;
use PDO;
use Throwable;

/**
 * Integrity boundary for permissioned Business + primary Branch onboarding (C-19).
 */
final class ListingOnboardingService
{
    private const METHODS = ['SIGNED_FORM', 'RECORDED_VISIT', 'VERIFIED_EMAIL', 'VERIFIED_PHONE'];

    public function __construct(private readonly AuditService $auditService = new AuditService()) {}

    /** @return array{ok:bool,error?:string,business_id?:int,branch_id?:int,permission_id?:int} */
    public function onboard(array $input, int $staffId): array
    {
        $data = $this->validate($input);
        if (isset($data['error'])) return ['ok' => false, 'error' => $data['error']];

        $pdo = Database::pdo();
        $mysqlLock = false;
        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $lock = $pdo->query("SELECT GET_LOCK('bulbula_listing_onboarding', 5)");
                $mysqlLock = (int) $lock->fetchColumn() === 1;
                if (!$mysqlLock) return ['ok' => false, 'error' => 'Another listing is being recorded. Please try again.'];
            }

            $pdo->beginTransaction();
            if ($this->findDuplicate($pdo, $data) !== null) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'A likely duplicate already exists with the same normalized business name, area, and address. Review the existing listing instead of creating another record.'];
            }

            $suffix = substr(bin2hex(random_bytes(5)), 0, 10);
            $slugBase = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $data['business_name_en']));
            $slugBase = trim($slugBase, '-') ?: 'bulbula-business';

            $stmt = $pdo->prepare('INSERT INTO businesses (slug, name_en, name_am, primary_category_id, brand_summary_en, lifecycle_status, created_by_staff_id) VALUES (:slug,:name_en,:name_am,:category,:summary,:status,:staff)');
            $stmt->execute([':slug'=>$slugBase.'-'.$suffix, ':name_en'=>$data['business_name_en'], ':name_am'=>$data['business_name_am'], ':category'=>$data['category_id'], ':summary'=>$data['summary_en'], ':status'=>'DRAFT', ':staff'=>$staffId]);
            $businessId = (int) $pdo->lastInsertId();

            $scopeNotes = 'Permission evidence reference: '.$data['evidence_reference'];
            $stmt = $pdo->prepare('INSERT INTO business_permissions (business_id,status,rep_full_name,rep_role_title,rep_phone,rep_email,permission_method,scope_notes,collected_by_staff_id,granted_at) VALUES (:business,:status,:name,:role,:phone,:email,:method,:notes,:staff,CURRENT_TIMESTAMP)');
            $stmt->execute([':business'=>$businessId, ':status'=>'GRANTED', ':name'=>$data['rep_name'], ':role'=>$data['rep_role'], ':phone'=>$data['rep_phone'], ':email'=>$data['rep_email'], ':method'=>$data['permission_method'], ':notes'=>$scopeNotes, ':staff'=>$staffId]);
            $permissionId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO branches (business_id,active_permission_id,branch_slug,branch_name_en,branch_name_am,is_primary_branch,area_id,sub_city_en,address_line_en,phone_primary,opening_hours_status,publication_status,is_verified,created_by_staff_id) VALUES (:business,:permission,:slug,:name_en,:name_am,1,:area,:sub_city,:address,:phone,:hours,:status,0,:staff)');
            $stmt->execute([':business'=>$businessId, ':permission'=>$permissionId, ':slug'=>$slugBase.'-main-'.$suffix, ':name_en'=>$data['business_name_en'].' (Bole Bulbula)', ':name_am'=>$data['business_name_am'], ':area'=>$data['area_id'], ':sub_city'=>'Bole', ':address'=>$data['address_en'], ':phone'=>$data['branch_phone'], ':hours'=>'UNKNOWN', ':status'=>'PENDING_QC', ':staff'=>$staffId]);
            $branchId = (int) $pdo->lastInsertId();
            $pdo->commit();

            try {
                $this->auditService->logStaffAction($staffId, 'LISTING_AND_PERMISSION_CREATED', 'branch', $branchId, null, ['business_id'=>$businessId, 'permission_id'=>$permissionId, 'status'=>'PENDING_QC', 'permission_method'=>$data['permission_method']]);
            } catch (Throwable $auditError) {
                error_log('Listing created but audit write failed: '.$auditError->getMessage());
            }
            try { Cache::invalidateTags(['branches', 'businesses', 'categories', 'areas']); } catch (Throwable $cacheError) { error_log('Listing cache invalidation failed: '.$cacheError->getMessage()); }
            return ['ok'=>true, 'business_id'=>$businessId, 'branch_id'=>$branchId, 'permission_id'=>$permissionId];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Listing onboarding failed: '.$e->getMessage());
            return ['ok'=>false, 'error'=>'The listing could not be recorded. No partial record was saved.'];
        } finally {
            if ($mysqlLock) {
                try { $pdo->query("SELECT RELEASE_LOCK('bulbula_listing_onboarding')"); } catch (Throwable) {}
            }
        }
    }

    private function validate(array $input): array
    {
        $text = static fn(string $key, int $max): string => mb_substr(trim((string)($input[$key] ?? '')), 0, $max);
        $data = [
            'business_name_en'=>$text('business_name_en', 180), 'business_name_am'=>$text('business_name_am', 180) ?: null,
            'category_id'=>(int)($input['primary_category_id'] ?? 0), 'area_id'=>(int)($input['area_id'] ?? 0),
            'address_en'=>$text('address_line_en', 300), 'branch_phone'=>$text('phone_primary', 40) ?: null,
            'summary_en'=>$text('brand_summary_en', 500) ?: null, 'rep_name'=>$text('rep_full_name', 160),
            'rep_role'=>$text('rep_role_title', 120), 'rep_phone'=>$text('rep_phone', 40),
            'rep_email'=>mb_strtolower($text('rep_email', 190)) ?: null, 'permission_method'=>$text('permission_method', 30),
            'evidence_reference'=>$text('permission_evidence_reference', 180),
        ];
        $inputError = self::validatePermissionInput($data);
        if ($inputError !== null) return ['error'=>$inputError];

        $category = Database::fetchOne('SELECT id FROM categories WHERE id=:id AND is_active=1 LIMIT 1', [':id'=>$data['category_id']]);
        $area = Database::fetchOne('SELECT id FROM areas WHERE id=:id AND is_active=1 LIMIT 1', [':id'=>$data['area_id']]);
        if ($category===null || $area===null) return ['error'=>'Select an active category and area from the controlled catalogue.'];
        return $data;
    }

    public static function validatePermissionInput(array $data): ?string
    {
        foreach (['business_name_en','address_en','rep_name','rep_role','rep_phone','evidence_reference'] as $required) {
            if (trim((string)($data[$required] ?? '')) === '') return 'Business, address, representative, representative role and phone, and permission evidence reference are required.';
        }
        $method = (string)($data['permission_method'] ?? '');
        if (!in_array($method, self::METHODS, true)) return 'Select a supported permission method.';
        $email = $data['rep_email'] ?? null;
        if ($method === 'VERIFIED_EMAIL' && (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) return 'A valid representative email is required for verified-email permission.';
        if (mb_strlen((string)$data['business_name_en']) < 2 || mb_strlen((string)$data['address_en']) < 5) return 'Enter a complete business name and physical address.';
        if (!preg_match('/^\+?[0-9][0-9 .()\-]{6,38}$/', (string)$data['rep_phone'])) return 'Enter a valid representative phone number.';
        return null;
    }

    public static function duplicateFingerprint(string $name, int $areaId, string $address): string
    {
        return hash('sha256', self::normalize($name).'|'.$areaId.'|'.self::normalize($address));
    }

    private function findDuplicate(PDO $pdo, array $data): ?array
    {
        $stmt = $pdo->prepare('SELECT br.id,bz.name_en,br.address_line_en FROM branches br INNER JOIN businesses bz ON bz.id=br.business_id WHERE br.area_id=:area');
        $stmt->execute([':area'=>$data['area_id']]);
        $fingerprint = self::duplicateFingerprint((string)$data['business_name_en'], (int)$data['area_id'], (string)$data['address_en']);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
            if (self::duplicateFingerprint((string)$candidate['name_en'], (int)$data['area_id'], (string)$candidate['address_line_en']) === $fingerprint) return $candidate;
        }
        return null;
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? $value;
    }
}
