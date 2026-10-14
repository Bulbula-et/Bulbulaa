<?php
declare(strict_types=1);
use Bulbula\Core\View;

$summary     = $summary ?? [];
$zeroQueries = $summary['zero_result_queries'] ?? [];
$topBranches = $summary['top_branches'] ?? [];
?>
<div class="b-grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));">
  <section>
    <h2 style="font-size: var(--text-lg); margin-bottom: var(--space-3);">Zero-Result Search Queries (C-28 Pilot Coverage Signal)</h2>
    <div class="b-table-wrap">
      <table class="b-table">
        <thead>
          <tr>
            <th>Normalized Query</th>
            <th>Count</th>
            <th>Last Searched</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($zeroQueries as $zq): ?>
            <tr>
              <td><strong><?= View::e($zq['query_normalized']) ?></strong></td>
              <td class="b-tabular"><?= (int) $zq['search_count'] ?></td>
              <td class="b-tabular" style="font-size: var(--text-xs);"><?= View::e($zq['last_searched_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section>
    <h2 style="font-size: var(--text-lg); margin-bottom: var(--space-3);">Branch Discovery &amp; Contact Actions (C-28, C-38)</h2>
    <div class="b-table-wrap">
      <table class="b-table">
        <thead>
          <tr>
            <th>Branch</th>
            <th>Profile Views</th>
            <th>Call Clicks</th>
            <th>Directions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topBranches as $tb): ?>
            <tr>
              <td><strong><?= View::e($tb['branch_name_en']) ?></strong></td>
              <td class="b-tabular"><?= (int) $tb['views'] ?></td>
              <td class="b-tabular"><?= (int) $tb['calls'] ?></td>
              <td class="b-tabular"><?= (int) $tb['directions'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
