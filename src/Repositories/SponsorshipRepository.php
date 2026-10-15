<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Cache;
use Bulbula\Core\Database;
use Bulbula\Domain\Enums\CampaignStatus;

/**
 * Staff-Managed Sponsorship Campaigns Repository (C-16, C-27, Section 07).
 *
 * V1 Rules:
 * - Only returns active, approved, time-bounded campaigns when Gate D-39 is enabled
 *   (or for preview demonstration when explicitly requested by Staff Console).
 * - Sponsored slots are always returned in a dedicated array, never mixed into organic results.
 */
final class SponsorshipRepository
{
    /**
     * Fetches active sponsored placements for a given format and optional category/area target.
     */
    public function findActivePlacements(
        string $placementFormat,
        ?int $categoryId = null,
        ?int $areaId = null,
        int $limit = 2
    ): array {
        $cacheKey = sprintf('campaigns:active:%s:%d:%d:%d', $placementFormat, (int) $categoryId, (int) $areaId, $limit);

        return Cache::remember($cacheKey, 300, ['campaigns', 'branches', 'gates'], static function () use ($placementFormat, $categoryId, $areaId, $limit): array {
            $now = date('Y-m-d H:i:s');
            $conditions = [
                "sc.status = 'ACTIVE'",
                'sc.placement_format = :format',
                'sc.starts_at <= :now_start',
                'sc.ends_at >= :now_end',
                "br.publication_status = 'PUBLISHED'",
            ];
            $params = [
                ':format'    => $placementFormat,
                ':now_start' => $now,
                ':now_end'   => $now,
            ];

            if ($categoryId !== null && $categoryId > 0) {
                $conditions[] = '(sc.target_category_id = :cat_id OR sc.target_category_id IS NULL)';
                $params[':cat_id'] = $categoryId;
            }

            if ($areaId !== null && $areaId > 0) {
                $conditions[] = '(sc.target_area_id = :area_id OR sc.target_area_id IS NULL)';
                $params[':area_id'] = $areaId;
            }

            $whereSql  = implode(' AND ', $conditions);
            $safeLimit = max(1, min(6, $limit));

            return Database::fetchAll(
                "SELECT sc.id AS campaign_id, sc.campaign_code, sc.placement_format, sc.package_name,
                        sc.starts_at, sc.ends_at,
                        br.id AS branch_id, br.branch_slug, br.branch_name_en, br.branch_name_am,
                        br.address_line_en, br.phone_primary, br.is_verified,
                        br.published_rating_avg, br.published_review_count,
                        bz.slug AS business_slug, bz.name_en AS business_name_en, bz.name_am AS business_name_am,
                        bz.brand_summary_en,
                        c.name_en AS primary_category_name_en, c.slug AS primary_category_slug,
                        a.name_en AS area_name_en, a.slug AS area_slug
                 FROM sponsorship_campaigns sc
                 INNER JOIN branches br ON br.id = sc.branch_id
                 INNER JOIN businesses bz ON bz.id = sc.business_id
                 INNER JOIN categories c ON c.id = bz.primary_category_id
                 INNER JOIN areas a ON a.id = br.area_id
                 WHERE {$whereSql}
                 ORDER BY sc.starts_at DESC
                 LIMIT {$safeLimit}",
                $params
            );
        });
    }

    public function listAllForStaff(): array
    {
        return Database::fetchAll(
            'SELECT sc.*,
                    bz.name_en AS business_name_en,
                    br.branch_name_en,
                    br.publication_status AS branch_publication_status,
                    c.name_en AS target_category_name,
                    a.name_en AS target_area_name,
                    COALESCE((SELECT SUM(impressions_count) FROM sponsorship_daily_metrics m WHERE m.campaign_id = sc.id), 0) AS total_impressions,
                    COALESCE((SELECT SUM(clicks_count) FROM sponsorship_daily_metrics m WHERE m.campaign_id = sc.id), 0) AS total_clicks
             FROM sponsorship_campaigns sc
             INNER JOIN businesses bz ON bz.id = sc.business_id
             INNER JOIN branches br ON br.id = sc.branch_id
             LEFT JOIN categories c ON c.id = sc.target_category_id
             LEFT JOIN areas a ON a.id = sc.target_area_id
             ORDER BY sc.created_at DESC'
        );
    }

    public function findById(int $id): ?array
    {
        return Database::fetchOne('SELECT sc.*,s.email AS creator_email,s.full_name AS creator_name,br.branch_name_en,bz.name_en AS business_name_en FROM sponsorship_campaigns sc INNER JOIN staff_users s ON s.id=sc.created_by_staff_id INNER JOIN branches br ON br.id=sc.branch_id INNER JOIN businesses bz ON bz.id=sc.business_id WHERE sc.id = :id LIMIT 1', [':id' => $id]);
    }

    public function terminateEarly(int $campaignId,int $adminId,string $reason): bool
    {
        return Database::execute("UPDATE sponsorship_campaigns SET status='TERMINATED_EARLY',terminated_by_admin_id=:admin,terminated_at=CURRENT_TIMESTAMP,termination_reason=:reason WHERE id=:id AND status IN ('ACTIVE','APPROVED_SCHEDULED')",[':admin'=>$adminId,':reason'=>$reason,':id'=>$campaignId])===1;
    }

    public function createDraftCampaign(array $data): int
    {
        $id = Database::insert(
            "INSERT INTO sponsorship_campaigns (
                campaign_code, business_id, branch_id, placement_format,
                target_category_id, target_area_id, package_name, agreed_price_etb,
                starts_at, ends_at, status, created_by_staff_id
            ) VALUES (
                :code, :biz_id, :br_id, :format,
                :cat_id, :area_id, :pkg, :price,
                :starts, :ends, 'PENDING_ADMIN_APPROVAL', :staff_id
            )",
            [
                ':code'     => trim((string) $data['campaign_code']),
                ':biz_id'   => (int) $data['business_id'],
                ':br_id'    => (int) $data['branch_id'],
                ':format'   => (string) $data['placement_format'],
                ':cat_id'   => !empty($data['target_category_id']) ? (int) $data['target_category_id'] : null,
                ':area_id'  => !empty($data['target_area_id']) ? (int) $data['target_area_id'] : null,
                ':pkg'      => trim((string) $data['package_name']),
                ':price'    => (float) $data['agreed_price_etb'],
                ':starts'   => (string) $data['starts_at'],
                ':ends'     => (string) $data['ends_at'],
                ':staff_id' => (int) $data['created_by_staff_id'],
            ]
        );
        Cache::invalidateTags(['campaigns']);
        return $id;
    }

    public function approveAndActivate(int $campaignId, int $adminStaffId): void
    {
        Database::execute(
            "UPDATE sponsorship_campaigns
             SET status = 'ACTIVE',
                 approved_by_admin_id = :admin_id,
                 approved_at = CURRENT_TIMESTAMP
             WHERE id = :id",
            [':admin_id' => $adminStaffId, ':id' => $campaignId]
        );
        Cache::invalidateTags(['campaigns']);
    }

    /**
     * C-27, C-28 Record impression or click only (calls, visits, or sales are never claimed).
     */
    public function incrementMetric(int $campaignId, string $surface, string $type): void
    {
        $col   = $type === 'CLICK' ? 'clicks_count' : 'impressions_count';
        $today = date('Y-m-d');

        try {
            $eligible=Database::fetchOne("SELECT id FROM sponsorship_campaigns WHERE id=:id AND status='ACTIVE' AND starts_at<=CURRENT_TIMESTAMP AND ends_at>=CURRENT_TIMESTAMP LIMIT 1",[':id'=>$campaignId]);
            if($eligible===null)return;
            $existing = Database::fetchOne(
                'SELECT impressions_count, clicks_count FROM sponsorship_daily_metrics
                 WHERE campaign_id = :cid AND metric_date = :dt AND surface = :surf',
                [':cid' => $campaignId, ':dt' => $today, ':surf' => $surface]
            );
            if ($existing === null) {
                Database::execute(
                    'INSERT INTO sponsorship_daily_metrics (campaign_id, metric_date, surface, impressions_count, clicks_count)
                     VALUES (:cid, :dt, :surf, :imp, :clk)',
                    [
                        ':cid'  => $campaignId,
                        ':dt'   => $today,
                        ':surf' => $surface,
                        ':imp'  => $type === 'IMPRESSION' ? 1 : 0,
                        ':clk'  => $type === 'CLICK' ? 1 : 0,
                    ]
                );
            } else {
                Database::execute(
                    "UPDATE sponsorship_daily_metrics
                     SET {$col} = {$col} + 1
                     WHERE campaign_id = :cid AND metric_date = :dt AND surface = :surf",
                    [':cid' => $campaignId, ':dt' => $today, ':surf' => $surface]
                );
            }
        } catch (\Throwable) {
            // Non-blocking metric recording
        }
    }
}
