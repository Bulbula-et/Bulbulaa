<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Cache;
use Bulbula\Core\Database;

/**
 * Controlled Local Areas & Sub-Cities Repository (C-05, C-24, Gate D-40).
 */
final class AreaRepository
{
    public function listAll(bool $activeOnly = true): array
    {
        $cacheKey = 'areas:list:' . ($activeOnly ? 'active' : 'all');

        return Cache::remember($cacheKey, 900, ['areas', 'branches'], static function () use ($activeOnly): array {
            $where = $activeOnly ? 'WHERE a.is_active = 1' : '';
            $sql = "
                SELECT a.*,
                       (
                           SELECT COUNT(*)
                           FROM branches br
                           WHERE br.area_id = a.id
                             AND br.publication_status = 'PUBLISHED'
                       ) AS published_branch_count
                FROM areas a
                {$where}
                ORDER BY a.is_launch_area DESC, a.display_order ASC, a.name_en ASC
            ";
            return Database::fetchAll($sql);
        });
    }

    public function findBySlug(string $slug): ?array
    {
        return Cache::remember('areas:slug:' . $slug, 900, ['areas'], static function () use ($slug): ?array {
            $area = Database::fetchOne(
                'SELECT * FROM areas WHERE slug = :slug AND is_active = 1 LIMIT 1',
                [':slug' => $slug]
            );
            if ($area !== null) {
                $area['aliases'] = Database::fetchAll(
                    'SELECT alias_term, language_code FROM area_aliases WHERE area_id = :aid ORDER BY alias_term ASC',
                    [':aid' => $area['id']]
                );
            }
            return $area;
        });
    }

    public function findById(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM areas WHERE id = :id LIMIT 1', [':id' => $id]);
    }

    public function saveArea(array $data): int
    {
        if (!empty($data['id'])) {
            $id = (int) $data['id'];
            Database::execute(
                'UPDATE areas
                 SET slug = :slug, name_en = :name_en, name_am = :name_am,
                     sub_city_en = :sub_en, sub_city_am = :sub_am,
                     center_latitude = :lat, center_longitude = :lng,
                     is_launch_area = :launch, is_active = :active, display_order = :ord
                 WHERE id = :id',
                [
                    ':slug'   => trim((string) $data['slug']),
                    ':name_en'=> trim((string) $data['name_en']),
                    ':name_am'=> !empty($data['name_am']) ? trim((string) $data['name_am']) : null,
                    ':sub_en' => trim((string) $data['sub_city_en']),
                    ':sub_am' => !empty($data['sub_city_am']) ? trim((string) $data['sub_city_am']) : null,
                    ':lat'    => isset($data['center_latitude']) && $data['center_latitude'] !== '' ? (float) $data['center_latitude'] : null,
                    ':lng'    => isset($data['center_longitude']) && $data['center_longitude'] !== '' ? (float) $data['center_longitude'] : null,
                    ':launch' => !empty($data['is_launch_area']) ? 1 : 0,
                    ':active' => !empty($data['is_active']) ? 1 : 0,
                    ':ord'    => (int) ($data['display_order'] ?? 0),
                    ':id'     => $id,
                ]
            );
        } else {
            $id = Database::insert(
                'INSERT INTO areas (slug, name_en, name_am, sub_city_en, sub_city_am, center_latitude, center_longitude, is_launch_area, is_active, display_order)
                 VALUES (:slug, :name_en, :name_am, :sub_en, :sub_am, :lat, :lng, :launch, :active, :ord)',
                [
                    ':slug'   => trim((string) $data['slug']),
                    ':name_en'=> trim((string) $data['name_en']),
                    ':name_am'=> !empty($data['name_am']) ? trim((string) $data['name_am']) : null,
                    ':sub_en' => trim((string) $data['sub_city_en']),
                    ':sub_am' => !empty($data['sub_city_am']) ? trim((string) $data['sub_city_am']) : null,
                    ':lat'    => isset($data['center_latitude']) && $data['center_latitude'] !== '' ? (float) $data['center_latitude'] : null,
                    ':lng'    => isset($data['center_longitude']) && $data['center_longitude'] !== '' ? (float) $data['center_longitude'] : null,
                    ':launch' => !empty($data['is_launch_area']) ? 1 : 0,
                    ':active' => !empty($data['is_active']) ? 1 : 0,
                    ':ord'    => (int) ($data['display_order'] ?? 0),
                ]
            );
        }

        Cache::invalidateTags(['areas', 'branches']);
        return $id;
    }
}
