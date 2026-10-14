<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Cache;
use Bulbula\Core\Csrf;
use Bulbula\Core\Database;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Repositories\AreaRepository;
use Bulbula\Repositories\BranchRepository;
use Bulbula\Repositories\TaxonomyRepository;
use Bulbula\Services\AuditService;
use Bulbula\Services\MediaUploadService;
use Bulbula\Services\StaffAuthService;

/**
 * C-19, C-20, C-22 Staff Listing Creation, Editing & Media Management Controller.
 */
final class ListingController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly BranchRepository $branchRepo = new BranchRepository(),
        private readonly TaxonomyRepository $taxonomyRepo = new TaxonomyRepository(),
        private readonly AreaRepository $areaRepo = new AreaRepository(),
        private readonly AuditService $auditService = new AuditService(),
        private readonly MediaUploadService $mediaUpload = new MediaUploadService()
    ) {}

    public function index(Request $request): Response
    {
        $html = View::render('staff/listings', [
            'pageTitle'  => 'Listings & Permission Registry — Staff Console',
            'activeNav'  => 'listings',
            'staff'      => $this->staffAuth->currentStaff(),
            'branches'   => $this->branchRepo->listAllForStaffConsole(50),
            'categories' => $this->taxonomyRepo->getFullTree(false),
            'areas'      => $this->areaRepo->listAll(false),
        ], 'staff');

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/listings');
        }

        $bizNameEn   = trim((string) $request->input('business_name_en', ''));
        $bizNameAm   = trim((string) $request->input('business_name_am', '')) ?: null;
        $catId       = (int) $request->input('primary_category_id', 0);
        $areaId      = (int) $request->input('area_id', 0);
        $addressEn   = trim((string) $request->input('address_line_en', ''));
        $phone       = trim((string) $request->input('phone_primary', '')) ?: null;
        $summaryEn   = trim((string) $request->input('brand_summary_en', '')) ?: null;
        $repName     = trim((string) $request->input('rep_full_name', 'Authorized Manager'));
        $repRole     = trim((string) $request->input('rep_role_title', 'General Manager'));
        $permMethod  = (string) $request->input('permission_method', 'RECORDED_VISIT');

        if ($bizNameEn === '' || $catId <= 0 || $areaId <= 0 || $addressEn === '') {
            Session::flash('error', 'Business name, category, area, and physical address are required.');
            return Response::redirect('/staff/listings');
        }

        $staff   = $this->staffAuth->currentStaff();
        $staffId = (int) ($staff['id'] ?? 1);
        $slugBase = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $bizNameEn));
        $slugBase = trim($slugBase, '-') ?: 'bulbula-business';
        $bizSlug  = $slugBase . '-' . substr((string) time(), -4);
        $brSlug   = $slugBase . '-main-' . substr((string) time(), -4);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmtBiz = $pdo->prepare(
                'INSERT INTO businesses (slug, name_en, name_am, primary_category_id, brand_summary_en, lifecycle_status, created_by_staff_id)
                 VALUES (:slug, :name_en, :name_am, :cat_id, :summary, :status, :staff_id)'
            );
            $stmtBiz->execute([
                ':slug'     => $bizSlug,
                ':name_en'  => $bizNameEn,
                ':name_am'  => $bizNameAm,
                ':cat_id'   => $catId,
                ':summary'  => $summaryEn,
                ':status'   => 'DRAFT',
                ':staff_id' => $staffId,
            ]);
            $businessId = (int) $pdo->lastInsertId();

            $stmtPerm = $pdo->prepare(
                'INSERT INTO business_permissions (business_id, status, rep_full_name, rep_role_title, rep_phone, permission_method, collected_by_staff_id, granted_at)
                 VALUES (:biz_id, :status, :rep_name, :rep_role, :rep_phone, :method, :staff_id, CURRENT_TIMESTAMP)'
            );
            $stmtPerm->execute([
                ':biz_id'    => $businessId,
                ':status'    => 'GRANTED',
                ':rep_name'  => $repName,
                ':rep_role'  => $repRole,
                ':rep_phone' => $phone ?? '+251911000000',
                ':method'    => in_array($permMethod, ['SIGNED_FORM', 'RECORDED_VISIT', 'VERIFIED_EMAIL', 'VERIFIED_PHONE'], true) ? $permMethod : 'RECORDED_VISIT',
                ':staff_id'  => $staffId,
            ]);
            $permId = (int) $pdo->lastInsertId();

            $stmtBr = $pdo->prepare(
                'INSERT INTO branches (business_id, active_permission_id, branch_slug, branch_name_en, branch_name_am, is_primary_branch, area_id, sub_city_en, address_line_en, phone_primary, opening_hours_status, publication_status, is_verified, created_by_staff_id)
                 VALUES (:biz_id, :perm_id, :slug, :name_en, :name_am, 1, :area_id, :sub_city, :addr, :phone, :hours_status, :pub_status, 0, :staff_id)'
            );
            $stmtBr->execute([
                ':biz_id'       => $businessId,
                ':perm_id'      => $permId,
                ':slug'         => $brSlug,
                ':name_en'      => $bizNameEn . ' (Bole Bulbula)',
                ':name_am'      => $bizNameAm,
                ':area_id'      => $areaId,
                ':sub_city'     => 'Bole',
                ':addr'         => $addressEn,
                ':phone'        => $phone,
                ':hours_status' => 'UNKNOWN',
                ':pub_status'   => 'PENDING_QC',
                ':staff_id'     => $staffId,
            ]);
            $branchId = (int) $pdo->lastInsertId();

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Session::flash('error', 'Failed to create listing: ' . $e->getMessage());
            return Response::redirect('/staff/listings');
        }

        $this->auditService->logStaffAction(
            $staffId,
            'LISTING_AND_PERMISSION_CREATED',
            'branch',
            $branchId,
            null,
            ['business_id' => $businessId, 'permission_id' => $permId, 'status' => 'PENDING_QC']
        );
        Cache::flushAll();

        Session::flash('success', 'Listing "' . $bizNameEn . '" onboarded with GRANTED Permission (#' . $permId . ') and queued in Verification & QC (/staff/verification).');
        return Response::redirect('/staff/listings');
    }

    public function update(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/listings');
        }

        $branchId    = (int) $request->param('branch_id', 0);
        $branch      = $this->branchRepo->findByIdForStaff($branchId);
        if ($branch === null) {
            Session::flash('error', 'Branch not found.');
            return Response::redirect('/staff/listings');
        }

        $phone       = trim((string) $request->input('phone_primary', (string) ($branch['phone_primary'] ?? ''))) ?: null;
        $addressEn   = trim((string) $request->input('address_line_en', (string) ($branch['address_line_en'] ?? '')));
        $hoursStatus = (string) $request->input('opening_hours_status', (string) ($branch['opening_hours_status'] ?? 'UNKNOWN'));
        if (!in_array($hoursStatus, ['KNOWN', 'UNKNOWN', 'BY_APPOINTMENT'], true)) {
            $hoursStatus = 'UNKNOWN';
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE branches
             SET phone_primary = :phone,
                 address_line_en = :addr,
                 opening_hours_status = :hours_status,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            ':phone'        => $phone,
            ':addr'         => $addressEn,
            ':hours_status' => $hoursStatus,
            ':id'           => $branchId,
        ]);

        $staff   = $this->staffAuth->currentStaff();
        $staffId = (int) ($staff['id'] ?? 1);
        $this->auditService->logStaffAction(
            $staffId,
            'BRANCH_ATTRIBUTES_UPDATED',
            'branch',
            $branchId,
            ['phone_primary' => $branch['phone_primary'], 'opening_hours_status' => $branch['opening_hours_status']],
            ['phone_primary' => $phone, 'opening_hours_status' => $hoursStatus]
        );
        Cache::flushAll();

        Session::flash('success', 'Branch #' . $branchId . ' attributes updated and directory cache invalidated.');
        return Response::redirect('/staff/listings');
    }

    public function uploadMedia(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/listings');
        }

        $branchId = (int) $request->param('branch_id', 0);
        $branch   = $this->branchRepo->findByIdForStaff($branchId);
        if ($branch === null) {
            Session::flash('error', 'Branch not found.');
            return Response::redirect('/staff/listings');
        }

        $permId = (int) ($branch['active_permission_id'] ?? 0);
        if ($permId <= 0) {
            Session::flash('error', 'Media upload blocked: an active business permission record is required.');
            return Response::redirect('/staff/listings');
        }

        $altText = mb_substr(trim((string) $request->input('alt_text_en', (string) ($branch['branch_name_en'] ?? 'Branch Photo'))), 0, 180, 'UTF-8');
        if ($altText === '') $altText = (string) ($branch['branch_name_en'] ?? 'Branch Photo');
        $processed = $this->mediaUpload->process($request->file('branch_image') ?? [], $branchId);
        if (!$processed['ok']) {
            Session::flash('error', 'Media rejected: ' . (string) ($processed['error'] ?? 'Invalid image.'));
            return Response::redirect('/staff/listings');
        }
        $filePath = (string) $processed['file_path'];
        $staff    = $this->staffAuth->currentStaff();
        $staffId  = (int) ($staff['id'] ?? 1);

        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO media_assets (business_id, branch_id, permission_id, media_type, file_path, width_px, height_px, file_size_bytes, alt_text_en, display_order, is_active, uploaded_by_staff_id)
                 VALUES (:biz_id, :br_id, :perm_id, :mtype, :fpath, :width, :height, :bytes, :alt, 1, 1, :staff_id)'
            );
            $stmt->execute([
                ':biz_id'   => (int) $branch['business_id'],
                ':br_id'    => $branchId,
                ':perm_id'  => $permId,
                ':mtype'    => 'COVER',
                ':fpath'    => $filePath,
                ':width'    => (int) $processed['width'],
                ':height'   => (int) $processed['height'],
                ':bytes'    => (int) $processed['size'],
                ':alt'      => $altText,
                ':staff_id' => $staffId,
            ]);
            $mediaId = (int) Database::pdo()->lastInsertId();
        } catch (\Throwable $e) {
            $this->mediaUpload->deleteProcessed($filePath);
            Session::flash('error', 'Image was processed but its media record could not be saved.');
            return Response::redirect('/staff/listings');
        }

        $this->auditService->logStaffAction(
            $staffId,
            'PERMITTED_MEDIA_ATTACHED',
            'media_asset',
            $mediaId,
            null,
            ['branch_id' => $branchId, 'file_path' => $filePath]
        );
        Cache::flushAll();

        Session::flash('success', 'Permitted media asset (#' . $mediaId . ') attached to Branch #' . $branchId . '.');
        return Response::redirect('/staff/listings');
    }
}
