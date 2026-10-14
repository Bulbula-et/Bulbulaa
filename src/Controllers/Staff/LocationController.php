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
use Bulbula\Services\AuditService;
use Bulbula\Services\StaffAuthService;

/**
 * C-24 Controlled Local Area & Sub-City Management Controller.
 */
final class LocationController
{
    public function __construct(
        private readonly StaffAuthService $staffAuth = new StaffAuthService(),
        private readonly AreaRepository $areaRepo = new AreaRepository(),
        private readonly AuditService $auditService = new AuditService()
    ) {}

    public function index(Request $request): Response
    {
        $html = View::render('staff/locations', [
            'pageTitle' => 'Local Areas & Launch Boundary — Staff Console',
            'activeNav' => 'locations',
            'staff'     => $this->staffAuth->currentStaff(),
            'areas'     => $this->areaRepo->listAll(false),
        ], 'staff');

        return Response::html($html);
    }

    public function save(Request $request): Response
    {
        if (!Csrf::verify((string) $request->input('_csrf', ''))) {
            Session::flash('error', 'Security token expired.');
            return Response::redirect('/staff/locations');
        }

        $nameEn = trim((string) $request->input('name_en', ''));
        $nameAm = trim((string) $request->input('name_am', '')) ?: null;
        $slug   = strtolower(trim((string) $request->input('slug', '')));
        $lat    = is_numeric($request->input('center_latitude')) ? (float) $request->input('center_latitude') : null;
        $lng    = is_numeric($request->input('center_longitude')) ? (float) $request->input('center_longitude') : null;

        if ($nameEn === '' || $slug === '') {
            Session::flash('error', 'Area name and URL slug are required.');
            return Response::redirect('/staff/locations');
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO areas (slug, name_en, name_am, sub_city_en, sub_city_am, center_latitude, center_longitude, is_launch_area, is_active, display_order)
             VALUES (:slug, :name_en, :name_am, :sub_en, :sub_am, :lat, :lng, 1, 1, 99)'
        );
        $stmt->execute([
            ':slug'    => $slug,
            ':name_en' => $nameEn,
            ':name_am' => $nameAm,
            ':sub_en'  => 'Bole',
            ':sub_am'  => 'ቦሌ',
            ':lat'     => $lat,
            ':lng'     => $lng,
        ]);
        $newId = (int) Database::pdo()->lastInsertId();

        $staff = $this->staffAuth->currentStaff();
        $this->auditService->logStaffAction(
            (int) ($staff['id'] ?? 1),
            'AREA_CREATED',
            'area',
            $newId,
            null,
            ['slug' => $slug, 'name_en' => $nameEn]
        );
        Cache::flushAll();

        Session::flash('success', 'Local area "' . $nameEn . '" saved and directory cache invalidated.');
        return Response::redirect('/staff/locations');
    }
}
