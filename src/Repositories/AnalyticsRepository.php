<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Database;

/**
 * First-Party Server-Side Operational & Product Analytics Repository (C-28, C-38).
 * Rule: No third-party ad-tech SDKs or behavioural profiling.
 */
final class AnalyticsRepository
{
    public function logSearchQuery(
        string $surface,
        string $queryNormalized,
        ?int $areaId,
        ?int $categoryId,
        int $resultsCount
    ): void {
        if ($queryNormalized === '') {
            return;
        }

        try {
            Database::execute(
                'INSERT INTO search_query_logs (surface, query_normalized, area_id, category_id, results_count)
                 VALUES (:surf, :q, :aid, :cid, :cnt)',
                [
                    ':surf' => $surface,
                    ':q'    => mb_substr($queryNormalized, 0, 160, 'UTF-8'),
                    ':aid'  => $areaId,
                    ':cid'  => $categoryId,
                    ':cnt'  => max(0, $resultsCount),
                ]
            );
        } catch (\Throwable) {
            // Non-blocking analytics write
        }
    }

    public function incrementBranchAction(int $branchId, string $surface, string $metricColumn): void
    {
        $allowed = [
            'profile_views',
            'action_call_clicks',
            'action_map_clicks',
            'action_website_clicks',
            'action_email_clicks',
            'action_share_clicks',
        ];
        if (!in_array($metricColumn, $allowed, true)) {
            return;
        }

        $today = date('Y-m-d');
        try {
            $existing = Database::fetchOne(
                'SELECT branch_id FROM branch_daily_analytics WHERE branch_id = :bid AND metric_date = :dt AND surface = :surf LIMIT 1',
                [':bid' => $branchId, ':dt' => $today, ':surf' => $surface]
            );

            if ($existing === null) {
                Database::execute(
                    "INSERT INTO branch_daily_analytics (branch_id, metric_date, surface, {$metricColumn})
                     VALUES (:bid, :dt, :surf, 1)",
                    [':bid' => $branchId, ':dt' => $today, ':surf' => $surface]
                );
            } else {
                Database::execute(
                    "UPDATE branch_daily_analytics
                     SET {$metricColumn} = {$metricColumn} + 1
                     WHERE branch_id = :bid AND metric_date = :dt AND surface = :surf",
                    [':bid' => $branchId, ':dt' => $today, ':surf' => $surface]
                );
            }
        } catch (\Throwable) {
            // Non-blocking analytics write
        }
    }

    /**
     * C-28 Operational Analytics Summary for Pilot & Staff Console evaluation.
     */
    public function getOperationalSummary(): array
    {
        $totals = Database::fetchOne(
            "SELECT
                (SELECT COUNT(*) FROM branches WHERE publication_status = 'PUBLISHED') AS published_branches,
                (SELECT COUNT(*) FROM branches WHERE publication_status = 'PUBLISHED' AND is_verified = 1) AS verified_branches,
                (SELECT COUNT(*) FROM branches WHERE publication_status IN ('DRAFT', 'PENDING_QC')) AS draft_or_qc_branches,
                (SELECT COUNT(*) FROM reviews WHERE status = 'PENDING_MODERATION') AS pending_reviews,
                (SELECT COUNT(*) FROM public_reports WHERE workflow_status = 'OPEN') AS open_reports,
                (SELECT COALESCE(SUM(profile_views), 0) FROM branch_daily_analytics) AS total_profile_views,
                (SELECT COALESCE(SUM(action_call_clicks + action_map_clicks + action_website_clicks), 0) FROM branch_daily_analytics) AS total_contact_actions
            "
        ) ?? [];

        $zeroResultQueries = Database::fetchAll(
            'SELECT query_normalized, COUNT(*) AS search_count, MAX(created_at) AS last_searched_at
             FROM search_query_logs
             WHERE results_count = 0
             GROUP BY query_normalized
             ORDER BY search_count DESC, last_searched_at DESC
             LIMIT 10'
        );

        $topBranches = Database::fetchAll(
            'SELECT br.branch_name_en, bz.name_en AS business_name_en,
                    SUM(bda.profile_views) AS views,
                    SUM(bda.action_call_clicks) AS calls,
                    SUM(bda.action_map_clicks) AS directions
             FROM branch_daily_analytics bda
             INNER JOIN branches br ON br.id = bda.branch_id
             INNER JOIN businesses bz ON bz.id = br.business_id
             GROUP BY bda.branch_id, br.branch_name_en, bz.name_en
             ORDER BY views DESC
             LIMIT 10'
        );

        return [
            'totals'              => $totals,
            'zero_result_queries' => $zeroResultQueries,
            'top_branches'        => $topBranches,
        ];
    }
}
