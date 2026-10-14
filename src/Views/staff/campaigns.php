<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$campaigns   = $campaigns ?? [];
$gateD39Open = !empty($gateD39Open);
?>
<div class="b-card" style="margin-bottom: var(--space-5); padding: var(--space-4);">
  <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
    <div>
      <strong>Commercial Integrity Boundary (C-16, C-27, Gate D-39):</strong>
      <span style="font-size: var(--text-sm); color: var(--neutral-600);">
        3 Approved Formats Only &middot; Impressions &amp; Clicks Measured Only &middot; Never Alters Organic Ranking or Verification
      </span>
    </div>
    <span class="b-badge <?= $gateD39Open ? 'b-badge--verified' : 'b-badge--orange' ?>">
      Gate D-39: <?= $gateD39Open ? 'ACTIVE' : 'BLOCKED' ?>
    </span>
  </div>
</div>

<div class="b-table-wrap">
  <table class="b-table">
    <thead>
      <tr>
        <th>Code</th>
        <th>Branch &amp; Business</th>
        <th>Format (C-16)</th>
        <th>Target</th>
        <th>Period (Time-Bounded)</th>
        <th>Fixed Price (ETB)</th>
        <th>Impressions / Clicks</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($campaigns as $c): ?>
        <tr>
          <td style="font-family: var(--font-mono); font-size: var(--text-xs); font-weight: 700;"><?= View::e($c['campaign_code']) ?></td>
          <td><strong><?= View::e($c['branch_name_en']) ?></strong></td>
          <td><span class="b-badge b-badge--orange"><?= View::e($c['placement_format']) ?></span></td>
          <td><?= View::e($c['target_category_name'] ?? 'Homepage') ?> <?= !empty($c['target_area_name']) ? ('(' . View::e($c['target_area_name']) . ')') : '' ?></td>
          <td class="b-tabular" style="font-size: var(--text-xs);"><?= View::e(substr((string) $c['starts_at'], 0, 10)) ?> &rarr; <?= View::e(substr((string) $c['ends_at'], 0, 10)) ?></td>
          <td class="b-tabular"><?= number_format((float) $c['agreed_price_etb'], 2) ?> ETB</td>
          <td class="b-tabular"><?= (int) $c['total_impressions'] ?> imp / <?= (int) $c['total_clicks'] ?> clicks</td>
          <td><span class="b-badge b-badge--verified"><?= View::e($c['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
