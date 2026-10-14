<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Cache;
use Bulbula\Core\Database;
use Bulbula\Domain\ValueObjects\Coordinates;

/**
 * Physical Branch Repository (Page 3, C-01..C-12, C-19..C-22).
 * Handles all discovery queries with smart tag-based caching and non-persistent PDO.
 */
final class BranchRepository
{
    private const BASE_PUBLIC_SELECT = "
        SELECT
            br.id AS branch_id,
            br.business_id,
            br.active_permission_id,
            br.branch_slug,
            br.branch_name_en,
            br.branch_name_am,
            br.is_primary_branch,
            br.area_id,
            br.sub_city_en,
            br.address_line_en,
            br.landmark_notes_en,
            br.latitude,
            br.longitude,
            br.phone_primary,
            br.phone_secondary,
            br.branch_email,
            br.opening_hours_status,
            br.opening_hours_json,
            br.services_products_json,
            br.publication_status,
            br.is_verified,
            br.last_verified_at,
            br.published_at,
            br.published_rating_avg,
            br.published_review_count,
            bz.slug AS business_slug,
            bz.name_en AS business_name_en,
            bz.name_am AS business_name_am,
            bz.brand_summary_en,
            bz.brand_description_en,
            bz.website_url,
            bz.primary_category_id,
            c.slug AS primary_category_slug,
            c.name_en AS primary_category_name_en,
            c.name_am AS primary_category_name_am,
            c.icon_key AS primary_category_icon,
            a.slug AS area_slug,
            a.name_en AS area_name_en,
            a.name_am AS area_name_am
        FROM branches br
        INNER JOIN businesses bz ON bz.id = br.business_id
        INNER JOIN categories c ON c.id = bz.primary_category_id
        INNER JOIN areas a ON a.id = br.area_id
    ";

    /**
     * C-01 Homepage Featured / Verified Branches in Launch Area.
     */
    public function listPublishedForHome(int $limit = 9): array
    {
        return Cache::remember('branches:home:' . $limit, 600, ['branches'], function () use ($limit): array {
            $sql = self::BASE_PUBLIC_SELECT . "
                WHERE br.publication_status = 'PUBLISHED'
                ORDER BY br.is_verified DESC, COALESCE(br.published_rating_avg, 0) DESC, br.id ASC
                LIMIT " . max(1, min(24, $limit));
            return Database::fetchAll($sql);
        });
    }

    /**
     * C-02 Search across business names, branch names, categories, areas, and controlled aliases.
     * Organic ordering is strictly independent of paid sponsorship (C-16).
     */
    public function searchOrganic(
        string $query = '',
        ?int $categoryId = null,
        ?int $areaId = null,
        bool $verifiedOnly = false,
        int $limit = 30
    ): array {
        $qNorm = mb_strtolower(trim($query), 'UTF-8');
        $cacheKey = sprintf('branches:search:%s:%d:%d:%d:%d', md5($qNorm), (int) $categoryId, (int) $areaId, $verifiedOnly ? 1 : 0, $limit);

        return Cache::remember($cacheKey, 300, ['branches', 'categories', 'areas'], function () use ($qNorm, $categoryId, $areaId, $verifiedOnly, $limit): array {
            $conditions = ["br.publication_status = 'PUBLISHED'"];
            $params = [];

            if ($categoryId !== null && $categoryId > 0) {
                $conditions[] = "(
                    bz.primary_category_id = :cat_id_1
                    OR bz.primary_category_id IN (SELECT sub.id FROM categories sub WHERE sub.parent_id = :cat_id_2)
                    OR EXISTS (SELECT 1 FROM business_secondary_categories bsc WHERE bsc.business_id = bz.id AND bsc.category_id = :cat_id_3)
                )";
                $params[':cat_id_1'] = $categoryId;
                $params[':cat_id_2'] = $categoryId;
                $params[':cat_id_3'] = $categoryId;
            }

            if ($areaId !== null && $areaId > 0) {
                $conditions[] = 'br.area_id = :area_id';
                $params[':area_id'] = $areaId;
            }

            if ($verifiedOnly) {
                $conditions[] = 'br.is_verified = 1';
            }

            if ($qNorm !== '') {
                $like = '%' . $qNorm . '%';
                $conditions[] = "(
                    LOWER(bz.name_en) LIKE :q1
                    OR LOWER(COALESCE(bz.name_am, '')) LIKE :q2
                    OR LOWER(br.branch_name_en) LIKE :q3
                    OR LOWER(COALESCE(bz.brand_summary_en, '')) LIKE :q4
                    OR LOWER(c.name_en) LIKE :q5
                    OR LOWER(COALESCE(c.name_am, '')) LIKE :q6
                    OR LOWER(a.name_en) LIKE :q7
                    OR LOWER(COALESCE(br.landmark_notes_en, '')) LIKE :q8
                    OR EXISTS (
                        SELECT 1 FROM category_aliases ca
                        WHERE (ca.category_id = bz.primary_category_id
                               OR ca.category_id IN (SELECT bsc2.category_id FROM business_secondary_categories bsc2 WHERE bsc2.business_id = bz.id))
                          AND LOWER(ca.alias_term) LIKE :q9
                    )
                    OR EXISTS (
                        SELECT 1 FROM area_aliases aa
                        WHERE aa.area_id = br.area_id AND LOWER(aa.alias_term) LIKE :q10
                    )
                )";
                for ($i = 1; $i <= 10; $i++) {
                    $params[':q' . $i] = $like;
                }
            }

            $whereSql = implode(' AND ', $conditions);
            $safeLimit = max(1, min(60, $limit));

            $sql = self::BASE_PUBLIC_SELECT . "
                WHERE {$whereSql}
                ORDER BY br.is_verified DESC, COALESCE(br.published_rating_avg, 0) DESC, bz.name_en ASC
                LIMIT {$safeLimit}
            ";

            return Database::fetchAll($sql, $params);
        });
    }

    /**
     * C-03 Search Autocomplete across controlled Categories, Areas, and Published Branches.
     */
    public function autocompleteSuggestions(string $prefix, int $limit = 8): array
    {
        $clean = mb_strtolower(trim($prefix), 'UTF-8');
        if ($clean === '') {
            return [];
        }

        return Cache::remember('autocomplete:' . md5($clean), 300, ['branches', 'categories', 'areas'], function () use ($clean, $limit): array {
            $likePrefix = $clean . '%';
            $likeAny    = '%' . $clean . '%';
            $suggestions = [];

            // 1. Controlled Category / Subcategory matches
            $cats = Database::fetchAll(
                "SELECT DISTINCT c.slug, c.name_en, c.name_am, c.depth_level
                 FROM categories c
                 LEFT JOIN category_aliases ca ON ca.category_id = c.id
                 WHERE c.is_active = 1
                   AND (LOWER(c.name_en) LIKE :p1 OR LOWER(COALESCE(c.name_am, '')) LIKE :p2 OR LOWER(COALESCE(ca.alias_term, '')) LIKE :p3)
                 ORDER BY c.depth_level ASC, c.display_order ASC
                 LIMIT 3",
                [':p1' => $likeAny, ':p2' => $likeAny, ':p3' => $likePrefix]
            );
            foreach ($cats as $cat) {
                $suggestions[] = [
                    'type'     => 'CATEGORY',
                    'label_en' => $cat['name_en'],
                    'label_am' => $cat['name_am'],
                    'url'      => '/c/' . $cat['slug'],
                    'badge'    => ((int) $cat['depth_level'] === 1) ? 'Category' : 'Subcategory',
                ];
            }

            // 2. Controlled Area matches
            $areas = Database::fetchAll(
                "SELECT DISTINCT a.slug, a.name_en, a.name_am, a.sub_city_en
                 FROM areas a
                 LEFT JOIN area_aliases aa ON aa.area_id = a.id
                 WHERE a.is_active = 1
                   AND (LOWER(a.name_en) LIKE :a1 OR LOWER(COALESCE(a.name_am, '')) LIKE :a2 OR LOWER(COALESCE(aa.alias_term, '')) LIKE :a3)
                 LIMIT 2",
                [':a1' => $likeAny, ':a2' => $likeAny, ':a3' => $likePrefix]
            );
            foreach ($areas as $area) {
                $suggestions[] = [
                    'type'     => 'AREA',
                    'label_en' => $area['name_en'] . ' (' . $area['sub_city_en'] . ')',
                    'label_am' => $area['name_am'],
                    'url'      => '/a/' . $area['slug'],
                    'badge'    => 'Local Area',
                ];
            }

            // 3. Published Business / Branch matches
            $branches = Database::fetchAll(
                "SELECT br.branch_slug, br.branch_name_en, bz.slug AS business_slug, bz.name_en AS business_name_en,
                        bz.name_am AS business_name_am, a.name_en AS area_name_en, c.name_en AS category_name_en
                 FROM branches br
                 INNER JOIN businesses bz ON bz.id = br.business_id
                 INNER JOIN areas a ON a.id = br.area_id
                 INNER JOIN categories c ON c.id = bz.primary_category_id
                 WHERE br.publication_status = 'PUBLISHED'
                   AND (LOWER(bz.name_en) LIKE :b1 OR LOWER(COALESCE(bz.name_am, '')) LIKE :b2 OR LOWER(br.branch_name_en) LIKE :b3)
                 ORDER BY br.is_verified DESC
                 LIMIT 5",
                [':b1' => $likeAny, ':b2' => $likeAny, ':b3' => $likeAny]
            );
            foreach ($branches as $br) {
                $suggestions[] = [
                    'type'     => 'BRANCH',
                    'label_en' => $br['business_name_en'] . ' — ' . $br['area_name_en'],
                    'label_am' => $br['business_name_am'],
                    'url'      => '/b/' . $br['business_slug'] . '/' . $br['branch_slug'],
                    'badge'    => $br['category_name_en'],
                ];
            }

            return array_slice($suggestions, 0, $limit);
        });
    }

    /**
     * C-07 Nearby & Distance Discovery using Bounding-Box + PHP Haversine calculation
     * (Compatible with both MySQL 8 and SQLite without requiring PostGIS extensions).
     */
    public function findNearby(Coordinates $visitorCoords, int $radiusMeters = 5000, int $limit = 20): array
    {
        $box = $visitorCoords->boundingBox($radiusMeters);

        $sql = self::BASE_PUBLIC_SELECT . "
            WHERE br.publication_status = 'PUBLISHED'
              AND br.latitude IS NOT NULL
              AND br.longitude IS NOT NULL
              AND br.latitude BETWEEN :min_lat AND :max_lat
              AND br.longitude BETWEEN :min_lng AND :max_lng
        ";

        $rows = Database::fetchAll($sql, [
            ':min_lat' => $box['min_lat'],
            ':max_lat' => $box['max_lat'],
            ':min_lng' => $box['min_lng'],
            ':max_lng' => $box['max_lng'],
        ]);

        $qualified = [];
        foreach ($rows as $row) {
            $branchCoords = new Coordinates((float) $row['latitude'], (float) $row['longitude']);
            $distMeters   = $visitorCoords->distanceMetersTo($branchCoords);

            if ($distMeters <= $radiusMeters) {
                $row['distance_meters'] = $distMeters;
                $row['distance_label']  = Coordinates::formatDistanceLabel($distMeters);
                $qualified[] = $row;
            }
        }

        usort($qualified, static fn(array $a, array $b): int => $a['distance_meters'] <=> $b['distance_meters']);
        return array_slice($qualified, 0, $limit);
    }

    /**
     * C-08 Full Branch Profile lookup with Business context, Sibling Branches, Secondary Categories, and Permitted Media.
     */
    public function findPublicBranchProfile(string $businessSlug, ?string $branchSlug = null): ?array
    {
        $cacheKey = 'branch:profile:' . $businessSlug . ':' . ($branchSlug ?? 'primary');

        return Cache::remember($cacheKey, 600, ['branches', 'reviews'], function () use ($businessSlug, $branchSlug): ?array {
            $params = [':biz_slug' => $businessSlug];
            $branchFilter = $branchSlug !== null
                ? 'AND br.branch_slug = :br_slug'
                : 'ORDER BY br.is_primary_branch DESC, br.id ASC';

            if ($branchSlug !== null) {
                $params[':br_slug'] = $branchSlug;
            }

            $sql = self::BASE_PUBLIC_SELECT . "
                WHERE bz.slug = :biz_slug
                  AND br.publication_status IN ('PUBLISHED', 'TEMPORARILY_CLOSED')
                {$branchFilter}
                LIMIT 1
            ";

            $branch = Database::fetchOne($sql, $params);
            if ($branch === null) {
                return null;
            }

            // Load Secondary Categories (C-04)
            $branch['secondary_categories'] = Database::fetchAll(
                'SELECT c.id, c.slug, c.name_en, c.name_am
                 FROM business_secondary_categories bsc
                 INNER JOIN categories c ON c.id = bsc.category_id
                 WHERE bsc.business_id = :bid AND c.is_active = 1
                 ORDER BY c.display_order ASC',
                [':bid' => $branch['business_id']]
            );

            // Load Sibling Branches under the same Business (C-08 Multi-branch context)
            $branch['sibling_branches'] = Database::fetchAll(
                "SELECT br.id AS branch_id, br.branch_slug, br.branch_name_en, br.branch_name_am,
                        br.is_primary_branch, br.address_line_en, br.phone_primary, br.is_verified,
                        a.name_en AS area_name_en
                 FROM branches br
                 INNER JOIN areas a ON a.id = br.area_id
                 WHERE br.business_id = :bid
                   AND br.publication_status IN ('PUBLISHED', 'TEMPORARILY_CLOSED')
                 ORDER BY br.is_primary_branch DESC, br.id ASC",
                [':bid' => $branch['business_id']]
            );

            // Load Permitted Media (C-22)
            $branch['media'] = Database::fetchAll(
                'SELECT id, media_type, file_path, alt_text_en, branch_id
                 FROM media_assets
                 WHERE business_id = :bid
                   AND (branch_id IS NULL OR branch_id = :brid)
                   AND is_active = 1
                 ORDER BY display_order ASC, id ASC',
                [
                    ':bid'  => $branch['business_id'],
                    ':brid' => $branch['branch_id'],
                ]
            );

            return $branch;
        });
    }

    public function findByIdForStaff(int $branchId): ?array
    {
        return Database::fetchOne(
            self::BASE_PUBLIC_SELECT . ' WHERE br.id = :id LIMIT 1',
            [':id' => $branchId]
        );
    }

    public function listAllForStaffConsole(int $limit = 100): array
    {
        $sql = self::BASE_PUBLIC_SELECT . ' ORDER BY br.updated_at DESC, br.id DESC LIMIT ' . max(1, min(250, $limit));
        return Database::fetchAll($sql);
    }

    public function updateStatusAndVerification(
        int $branchId,
        string $publicationStatus,
        bool $isVerified,
        ?int $permissionId,
        int $staffId
    ): void {
        Database::execute(
            'UPDATE branches
             SET publication_status = :status,
                 is_verified = :verified,
                 active_permission_id = COALESCE(:perm_id, active_permission_id),
                 last_verified_at = CASE WHEN :verified_flag = 1 THEN CURRENT_TIMESTAMP ELSE last_verified_at END,
                 last_verified_by_staff_id = CASE WHEN :verified_flag_2 = 1 THEN :staff_id ELSE last_verified_by_staff_id END,
                 published_at = CASE WHEN :is_pub = 1 AND published_at IS NULL THEN CURRENT_TIMESTAMP ELSE published_at END,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            [
                ':status'          => $publicationStatus,
                ':verified'        => $isVerified ? 1 : 0,
                ':perm_id'         => $permissionId,
                ':verified_flag'   => $isVerified ? 1 : 0,
                ':verified_flag_2' => $isVerified ? 1 : 0,
                ':staff_id'        => $staffId,
                ':is_pub'          => $publicationStatus === 'PUBLISHED' ? 1 : 0,
                ':id'              => $branchId,
            ]
        );

        Cache::invalidateTags(['branches']);
    }
}
