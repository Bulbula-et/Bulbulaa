<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Cache;
use Bulbula\Core\Database;
use InvalidArgumentException;

/**
 * Brand-Level Business Repository (Page 3, C-04, C-08, C-19, C-20).
 * Rule: Brand-level facts belong to Business. Each published listing has
 *       exactly one Primary Category and zero or more Secondary Categories with no duplicates.
 */
final class BusinessRepository
{
    public function findById(int $id): ?array
    {
        $biz = Database::fetchOne(
            'SELECT bz.*, c.name_en AS primary_category_name_en, c.name_am AS primary_category_name_am, c.slug AS primary_category_slug
             FROM businesses bz
             INNER JOIN categories c ON c.id = bz.primary_category_id
             WHERE bz.id = :id
             LIMIT 1',
            [':id' => $id]
        );
        if ($biz !== null) {
            $biz['secondary_categories'] = $this->getSecondaryCategories((int) $biz['id']);
        }
        return $biz;
    }

    public function findBySlug(string $slug): ?array
    {
        return Cache::remember('business:slug:' . $slug, 600, ['branches', 'categories'], function () use ($slug): ?array {
            $biz = Database::fetchOne(
                'SELECT bz.*, c.name_en AS primary_category_name_en, c.name_am AS primary_category_name_am, c.slug AS primary_category_slug
                 FROM businesses bz
                 INNER JOIN categories c ON c.id = bz.primary_category_id
                 WHERE bz.slug = :slug
                 LIMIT 1',
                [':slug' => $slug]
            );
            if ($biz !== null) {
                $biz['secondary_categories'] = $this->getSecondaryCategories((int) $biz['id']);
            }
            return $biz;
        });
    }

    public function getSecondaryCategories(int $businessId): array
    {
        return Database::fetchAll(
            'SELECT c.id, c.slug, c.name_en, c.name_am
             FROM business_secondary_categories bsc
             INNER JOIN categories c ON c.id = bsc.category_id
             WHERE bsc.business_id = :bid AND c.is_active = 1
             ORDER BY c.display_order ASC, c.name_en ASC',
            [':bid' => $businessId]
        );
    }

    /**
     * Duplicate prospect check before field collection (C-19).
     */
    public function findPotentialDuplicates(string $nameEn, ?string $slug = null): array
    {
        $like = '%' . trim($nameEn) . '%';
        return Database::fetchAll(
            'SELECT id, slug, name_en, name_am, lifecycle_status
             FROM businesses
             WHERE name_en LIKE :name_like OR (:slug IS NOT NULL AND slug = :slug_exact)
             LIMIT 10',
            [
                ':name_like'  => $like,
                ':slug'       => $slug,
                ':slug_exact' => $slug,
            ]
        );
    }

    public function create(array $data, array $secondaryCategoryIds = []): int
    {
        $primaryCategoryId = (int) $data['primary_category_id'];

        $businessId = Database::insert(
            'INSERT INTO businesses (
                slug, name_en, name_am, primary_category_id, brand_summary_en,
                brand_description_en, website_url, lifecycle_status, created_by_staff_id
            ) VALUES (
                :slug, :name_en, :name_am, :primary_cat, :summary,
                :description, :website, :status, :staff_id
            )',
            [
                ':slug'        => trim((string) $data['slug']),
                ':name_en'     => trim((string) $data['name_en']),
                ':name_am'     => !empty($data['name_am']) ? trim((string) $data['name_am']) : null,
                ':primary_cat' => $primaryCategoryId,
                ':summary'     => !empty($data['brand_summary_en']) ? trim((string) $data['brand_summary_en']) : null,
                ':description' => !empty($data['brand_description_en']) ? trim((string) $data['brand_description_en']) : null,
                ':website'     => !empty($data['website_url']) ? trim((string) $data['website_url']) : null,
                ':status'      => (string) ($data['lifecycle_status'] ?? 'DRAFT'),
                ':staff_id'    => (int) $data['created_by_staff_id'],
            ]
        );

        $this->syncSecondaryCategories($businessId, $primaryCategoryId, $secondaryCategoryIds);
        Cache::invalidateTags(['branches', 'categories']);
        return $businessId;
    }

    /**
     * Enforces C-04 rule: Each listing has 1 Primary Category and 0..N Secondary Categories with no duplicates.
     *
     * @param int[] $secondaryCategoryIds
     */
    public function syncSecondaryCategories(int $businessId, int $primaryCategoryId, array $secondaryCategoryIds): void
    {
        Database::execute('DELETE FROM business_secondary_categories WHERE business_id = :bid', [':bid' => $businessId]);

        $uniqueSecondaries = array_unique(array_map('intval', $secondaryCategoryIds));
        foreach ($uniqueSecondaries as $catId) {
            if ($catId <= 0 || $catId === $primaryCategoryId) {
                continue; // Never duplicate Primary Category in Secondary Categories
            }
            Database::execute(
                'INSERT INTO business_secondary_categories (business_id, category_id) VALUES (:bid, :cid)',
                [':bid' => $businessId, ':cid' => $catId]
            );
        }
        Cache::invalidateTags(['branches', 'categories']);
    }
}
