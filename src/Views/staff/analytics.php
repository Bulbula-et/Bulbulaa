<?php
declare(strict_types=1);
use Bulbula\Core\View;

$summary = $summary ?? [];
$zeroQueries = $summary['zero_result_queries'] ?? [];
$topBranches = $summary['top_branches'] ?? [];
$dailyActivity = $summary['daily_activity'] ?? [];
$surfaceBreakdown = $summary['surface_breakdown'] ?? [];
$totalViews = 0;
$totalCalls = 0;
$totalDirections = 0;
$maxViews = 0;
foreach ($topBranches as $branch) {
    $views = (int) ($branch['views'] ?? 0);
    $totalViews += $views;
    $totalCalls += (int) ($branch['calls'] ?? 0);
    $totalDirections += (int) ($branch['directions'] ?? 0);
    $maxViews = max($maxViews, $views);
}
$zeroDemand = array_sum(array_map(static fn(array $query): int => (int) ($query['search_count'] ?? 0), $zeroQueries));
$actionRate = $totalViews > 0 ? (($totalCalls + $totalDirections) / $totalViews) * 100 : 0;
$activityChart = [
    'type' => 'line',
    'labels' => array_map(static fn(array $row): string => date('D', strtotime((string) $row['date'])), $dailyActivity),
    'datasets' => [
        ['label' => 'Profile views', 'data' => array_column($dailyActivity, 'views'), 'color' => '#397eb5', 'fill' => true],
        ['label' => 'Calls', 'data' => array_column($dailyActivity, 'calls'), 'color' => '#ea6a15'],
        ['label' => 'Directions', 'data' => array_column($dailyActivity, 'directions'), 'color' => '#2daf72'],
    ],
];
$surfaceChart = [
    'type' => 'doughnut', 'scales' => false,
    'labels' => array_map(static fn(array $row): string => ucwords(strtolower(str_replace('_', ' ', (string) $row['surface']))), $surfaceBreakdown),
    'datasets' => [['label' => 'Profile views', 'data' => array_map(static fn(array $row): int => (int) $row['views'], $surfaceBreakdown)]],
];
?>
<section class="b-analytics-summary" aria-label="Analytics summary">
  <article class="b-analytics-kpi b-analytics-kpi--blue"><span class="b-analytics-kpi__label">Profile discovery</span><strong class="b-analytics-kpi__value"><?= number_format($totalViews) ?></strong><span class="b-analytics-kpi__meta">Views across tracked branches</span></article>
  <article class="b-analytics-kpi b-analytics-kpi--green"><span class="b-analytics-kpi__label">Call actions</span><strong class="b-analytics-kpi__value"><?= number_format($totalCalls) ?></strong><span class="b-analytics-kpi__meta">High-intent contact activity</span></article>
  <article class="b-analytics-kpi b-analytics-kpi--navy"><span class="b-analytics-kpi__label">Direction actions</span><strong class="b-analytics-kpi__value"><?= number_format($totalDirections) ?></strong><span class="b-analytics-kpi__meta"><?= number_format($actionRate, 1) ?>% combined action rate</span></article>
  <article class="b-analytics-kpi b-analytics-kpi--orange"><span class="b-analytics-kpi__label">Unmet search demand</span><strong class="b-analytics-kpi__value"><?= number_format($zeroDemand) ?></strong><span class="b-analytics-kpi__meta"><?= count($zeroQueries) ?> zero-result query signals</span></article>
</section>

<section class="b-analytics-charts" aria-label="Seven-day performance charts">
  <article class="b-card b-chart-card">
    <header class="b-chart-card__head"><div><h2>Discovery and action trend</h2><p>Daily first-party activity recorded across public surfaces.</p></div><span class="b-chart-card__period">Last 7 days</span></header>
    <div class="b-chart-frame"><canvas data-bulbula-chart data-chart-config="<?= View::e((string) json_encode($activityChart)) ?>" role="img" aria-label="Seven-day line chart of profile views, calls, and direction requests">Seven-day activity: <?= number_format($totalViews) ?> views, <?= number_format($totalCalls) ?> calls, and <?= number_format($totalDirections) ?> direction requests.</canvas></div>
  </article>
  <article class="b-card b-chart-card">
    <header class="b-chart-card__head"><div><h2>Discovery surfaces</h2><p>Profile views by customer experience.</p></div><span class="b-chart-card__period">7 days</span></header>
    <div class="b-chart-frame b-chart-frame--compact"><canvas data-bulbula-chart data-chart-config="<?= View::e((string) json_encode($surfaceChart)) ?>" role="img" aria-label="Doughnut chart of profile views by discovery surface">Profile views are grouped by Web and Telegram Mini App activity.</canvas></div>
  </article>
</section>

<div class="b-analytics-grid">
  <section class="b-card b-analytics-panel">
    <header class="b-analytics-panel__head"><div><h2>Coverage opportunities</h2><p>Zero-result searches reveal missing categories and local supply.</p></div><span class="b-analytics-panel__tag">Coverage signal</span></header>
    <?php if ($zeroQueries === []): ?>
      <div class="b-analytics-empty"><strong>No unmet searches recorded</strong>Coverage signals will appear when searches return no directory results.</div>
    <?php else: ?>
      <div class="b-search-demand">
        <?php foreach ($zeroQueries as $query): ?>
          <div class="b-search-demand__row"><div><span class="b-search-demand__query"><?= View::e($query['query_normalized']) ?></span><span class="b-search-demand__date">Last searched <?= View::e($query['last_searched_at']) ?></span></div><span class="b-search-demand__count"><?= (int) $query['search_count'] ?> searches</span></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="b-card b-analytics-panel">
    <header class="b-analytics-panel__head"><div><h2>Branch discovery performance</h2><p>Organic profile reach and high-intent customer actions.</p></div><span class="b-analytics-panel__tag">First-party analytics</span></header>
    <?php if ($topBranches === []): ?>
      <div class="b-analytics-empty"><strong>No branch analytics available</strong>Performance appears after profile views and actions are recorded.</div>
    <?php else: ?>
      <div class="b-branch-performance">
        <?php foreach ($topBranches as $branch): ?>
          <?php $views = (int) ($branch['views'] ?? 0); $width = $maxViews > 0 ? max(3, ($views / $maxViews) * 100) : 0; ?>
          <div class="b-performance-row">
            <div class="b-performance-row__top"><span class="b-performance-row__name"><?= View::e($branch['branch_name_en']) ?></span><span class="b-performance-row__views"><?= number_format($views) ?> views</span></div>
            <div class="b-performance-track" role="img" aria-label="<?= View::e($branch['branch_name_en']) ?>: <?= number_format($views) ?> profile views"><span class="b-performance-fill" style="width:<?= number_format($width, 1, '.', '') ?>%"></span></div>
            <div class="b-performance-row__actions"><span><strong><?= number_format((int) $branch['calls']) ?></strong> call clicks</span><span><strong><?= number_format((int) $branch['directions']) ?></strong> direction requests</span></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
