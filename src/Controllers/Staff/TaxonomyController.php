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
use Bulbula\Repositories\TaxonomyRepository;
use Bulbula\Services\AuditService;
use Bulbula\Services\StaffAuthService;

/**
 * C-23 Controlled 2-Level Category Management Controller.
 */
final class TaxonomyController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly TaxonomyRepository $taxonomyRepo = new TaxonomyRepository(),
        private readonly AuditService $auditService = new AuditService()
    ) {}

    public function index(Request $request): Response
    {
        $html = View::render('staff/taxonomy', [
            'pageTitle'  => '2-Level Taxonomy Catalogue (C-23) — Staff Console',
            'activeNav'  => 'taxonomy',
            'staff'      => $this->staffAuth->currentStaff(),
            'categories' => $this->taxonomyRepo->getFullTree(false),
        ], 'staff');

        return Response::html($html);
    }

    public function save(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/taxonomy');
        }

        $nameEn   = trim((string) $request->input('name_en', ''));
        $nameAm   = trim((string) $request->input('name_am', '')) ?: null;
        $slug     = strtolower(trim((string) $request->input('slug', '')));
        $parentId = $request->input('parent_id') ? (int) $request->input('parent_id') : null;

        if ($nameEn === '' || $slug === '') {
            Session::flash('error', 'English label and URL slug are required.');
            return Response::redirect('/staff/taxonomy');
        }

        $depth = $parentId ? 2 : 1;
        $stmt  = Database::pdo()->prepare(
            'INSERT INTO categories (parent_id, depth_level, slug, name_en, name_am, is_active, display_order)
             VALUES (:parent_id, :depth_level, :slug, :name_en, :name_am, 1, 99)'
        );
        $stmt->execute([
            ':parent_id'   => $parentId,
            ':depth_level' => $depth,
            ':slug'        => $slug,
            ':name_en'     => $nameEn,
            ':name_am'     => $nameAm,
        ]);
        $newId = (int) Database::pdo()->lastInsertId();

        $staff = $this->staffAuth->currentStaff();
        $this->auditService->logStaffAction(
            (int) ($staff['id'] ?? 1),
            'CATEGORY_CREATED',
            'category',
            $newId,
            null,
            ['slug' => $slug, 'name_en' => $nameEn, 'depth_level' => $depth]
        );
        Cache::flushAll();

        Session::flash('success', 'Taxonomy entry "' . $nameEn . '" saved and directory cache invalidated.');
        return Response::redirect('/staff/taxonomy');
    }
}
