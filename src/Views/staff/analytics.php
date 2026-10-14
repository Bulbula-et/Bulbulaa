<?php
declare(strict_types=1);
use Bulbula\Core\View;

$summary = $summary ?? [];
$zeroQueries = $summary['zero_result_queries'] ?? [];
$topBranches = $summary['top_branches'] ?? [];
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
?>
<section class="b-analytics-summary" aria-label="Analytics summary">
  <article class="b-analytics-kpi b-analytics-kpi--blue"><span class="b-analytics-kpi__label">Profile discovery</span><strong class="b-analytics-kpi__value"><?= number_format($totalViews) ?></strong><span class="b-analytics-kpi__meta">Views across tracked branches</span></article>
  <article class="b-analytics-kpi b-analytics-kpi--green"><span class="b-analytics-kpi__label">Call actions</span><strong class="b-analytics-kpi__value"><?= number_format($totalCalls) ?></strong><span class="b-analytics-kpi__meta">High-intent contact activity</span></article>
  <article class="b-analytics-kpi b-analytics-kpi--navy"><span class="b-analytics-kpi__label">Direction actions</span><strong class="b-analytics-kpi__value"><?= number_format($totalDirections) ?></strong><span class="b-analytics-kpi__meta"><?= number_format($actionRate, 1) ?>% combined action rate</span></article>
  <article class="b-analytics-kpi b-analytics-kpi--orange"><span class="b-analytics-kpi__label">Unmet search demand</span><strong class="b-analytics-kpi__value"><?= number_format($zeroDemand) ?></strong><span class="b-analytics-kpi__meta"><?= count($zeroQueries) ?> zero-result query signals</span></article>
</section>

<div class="b-analytics-grid">
  <section class="b-card b-analytics-panel">
    <header class="b-analytics-panel__head"><div><h2>Coverage opportunities</h2><p>Zero-result searches reveal missing categories and local supply.</p></div><span class="b-analytics-panel__tag">Pilot signal C-28</span></header>
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
    <header class="b-analytics-panel__head"><div><h2>Branch discovery performance</h2><p>Organic profile reach and high-intent customer actions.</p></div><span class="b-analytics-panel__tag">Controls C-28 + C-38</span></header>
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
