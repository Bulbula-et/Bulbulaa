<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Cache;
use Bulbula\Core\Database;
use InvalidArgumentException;

/**
 * Controlled Two-Level Taxonomy & Alias Repository (C-04, C-23).
 * Rule: Exactly two levels (Level 1 Category -> Level 2 Subcategory). Treated as reference data.
 */
final class TaxonomyRepository
{
    /**
     * Returns the full 2-level Category -> Subcategories tree with published listing counts.
     */
    public function getFullTree(bool $activeOnly = true): array
    {
        $cacheKey = 'taxonomy:tree:' . ($activeOnly ? 'active' : 'all');

        return Cache::remember($cacheKey, 900, ['categories', 'branches'], static function () use ($activeOnly): array {
            $where = $activeOnly ? 'WHERE c.is_active = 1' : '';
            $sql = "
                SELECT c.*,
                       (
                           SELECT COUNT(DISTINCT br.id)
                           FROM branches br
                           INNER JOIN businesses bz ON bz.id = br.business_id
                           LEFT JOIN business_secondary_categories bsc ON bsc.business_id = bz.id
                           WHERE br.publication_status = 'PUBLISHED'
                             AND (
                                 bz.primary_category_id = c.id
                                 OR bsc.category_id = c.id
                                 OR bz.primary_category_id IN (SELECT sub.id FROM categories sub WHERE sub.parent_id = c.id)
                             )
                       ) AS published_branch_count
                FROM categories c
                {$where}
                ORDER BY c.depth_level ASC, c.display_order ASC, c.name_en ASC
            ";

            $rows = Database::fetchAll($sql);
            $parents = [];
            $childrenByParent = [];

            foreach ($rows as $row) {
                if ($row['parent_id'] === null) {
                    $row['subcategories'] = [];
                    $parents[(int) $row['id']] = $row;
                } else {
                    $childrenByParent[(int) $row['parent_id']][] = $row;
                }
            }

            foreach ($childrenByParent as $parentId => $subs) {
                if (isset($parents[$parentId])) {
                    $parents[$parentId]['subcategories'] = $subs;
                }
            }

            return array_values($parents);
        });
    }

    public function findBySlug(string $slug): ?array
    {
        return Cache::remember('taxonomy:slug:' . $slug, 900, ['categories'], static function () use ($slug): ?array {
            $category = Database::fetchOne(
                'SELECT * FROM categories WHERE slug = :slug AND is_active = 1 LIMIT 1',
                [':slug' => $slug]
            );
            if ($category === null) {
                return null;
            }

            if ($category['parent_id'] === null) {
                $category['subcategories'] = Database::fetchAll(
                    'SELECT * FROM categories WHERE parent_id = :pid AND is_active = 1 ORDER BY display_order ASC, name_en ASC',
                    [':pid' => $category['id']]
                );
                $category['parent'] = null;
            } else {
                $category['subcategories'] = [];
                $category['parent'] = Database::fetchOne(
                    'SELECT * FROM categories WHERE id = :pid LIMIT 1',
                    [':pid' => $category['parent_id']]
                );
            }

            $category['aliases'] = Database::fetchAll(
                'SELECT alias_term, language_code FROM category_aliases WHERE category_id = :cid ORDER BY alias_term ASC',
                [':cid' => $category['id']]
            );

            return $category;
        });
    }

    public function findById(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM categories WHERE id = :id LIMIT 1', [':id' => $id]);
    }

    /**
     * Creates or updates a Category or Subcategory, strictly enforcing depth_level IN (1, 2) (C-23).
     */
    public function saveCategory(array $data): int
    {
        $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        $depth    = 1;

        if ($parentId !== null) {
            $parent = $this->findById($parentId);
            if ($parent === null) {
                throw new InvalidArgumentException('Parent category does not exist.');
            }
            if ((int) $parent['depth_level'] !== 1) {
                throw new InvalidArgumentException('Bulbula supports exactly two taxonomy levels: category and subcategory.');
            }
            $depth = 2;
        }

        if (!empty($data['id'])) {
            $id = (int) $data['id'];
            Database::execute(
                'UPDATE categories
                 SET parent_id = :parent_id, depth_level = :depth, slug = :slug,
                     name_en = :name_en, name_am = :name_am, description_en = :desc_en,
                     icon_key = :icon, display_order = :ord, is_active = :active
                 WHERE id = :id',
                [
                    ':parent_id' => $parentId,
                    ':depth'     => $depth,
                    ':slug'      => trim((string) $data['slug']),
                    ':name_en'   => trim((string) $data['name_en']),
                    ':name_am'   => !empty($data['name_am']) ? trim((string) $data['name_am']) : null,
                    ':desc_en'   => !empty($data['description_en']) ? trim((string) $data['description_en']) : null,
                    ':icon'      => !empty($data['icon_key']) ? trim((string) $data['icon_key']) : 'grid',
                    ':ord'       => (int) ($data['display_order'] ?? 0),
                    ':active'    => !empty($data['is_active']) ? 1 : 0,
                    ':id'        => $id,
                ]
            );
        } else {
            $id = Database::insert(
                'INSERT INTO categories (parent_id, depth_level, slug, name_en, name_am, description_en, icon_key, display_order, is_active)
                 VALUES (:parent_id, :depth, :slug, :name_en, :name_am, :desc_en, :icon, :ord, :active)',
                [
                    ':parent_id' => $parentId,
                    ':depth'     => $depth,
                    ':slug'      => trim((string) $data['slug']),
                    ':name_en'   => trim((string) $data['name_en']),
                    ':name_am'   => !empty($data['name_am']) ? trim((string) $data['name_am']) : null,
                    ':desc_en'   => !empty($data['description_en']) ? trim((string) $data['description_en']) : null,
                    ':icon'      => !empty($data['icon_key']) ? trim((string) $data['icon_key']) : 'grid',
                    ':ord'       => (int) ($data['display_order'] ?? 0),
                    ':active'    => !empty($data['is_active']) ? 1 : 0,
                ]
            );
        }

        Cache::invalidateTags(['categories', 'branches']);
        return $id;
    }
}
