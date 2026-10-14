<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$campaigns   = $campaigns ?? [];
$branches    = $branches ?? [];
$gateD39Open = !empty($gateD39Open);
?>
<div class="b-card" style="margin-bottom: var(--space-5); padding: var(--space-4);">
  <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; margin-bottom: var(--space-4);">
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

  <form action="/staff/campaigns/create" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: var(--space-3); align-items: end; padding-top: var(--space-3); border-top: 1px solid var(--color-border-subtle);">
    <?= Csrf::field() ?>
    <div>
      <label class="b-label" for="cmp-branch">Published Branch</label>
      <select id="cmp-branch" name="branch_id" class="b-select" required>
        <?php foreach ($branches as $br): ?>
          <option value="<?= (int) $br['branch_id'] ?>"><?= View::e($br['branch_name_en']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="b-label" for="cmp-format">Placement Format (C-16)</label>
      <select id="cmp-format" name="placement_format" class="b-select">
        <option value="HOMEPAGE_PROMOTION">HOMEPAGE_PROMOTION</option>
        <option value="CATEGORY_SPONSORSHIP">CATEGORY_SPONSORSHIP</option>
        <option value="SEARCH_PLACEMENT">SEARCH_PLACEMENT</option>
      </select>
    </div>
    <div>
      <label class="b-label" for="cmp-price">Fixed Price (ETB)</label>
      <input id="cmp-price" type="number" step="0.01" name="agreed_price_etb" class="b-input" value="4500.00" required>
    </div>
    <div>
      <label class="b-label" for="cmp-days">Duration (Days)</label>
      <input id="cmp-days" type="number" name="duration_days" class="b-input" value="30" min="1" max="365" required>
    </div>
    <div>
      <button type="submit" class="b-btn b-btn--primary b-btn--sm" style="width: 100%;">Record Campaign</button>
    </div>
  </form>
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
        <th>Status &amp; Action</th>
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
          <td>
            <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap;">
              <span class="b-badge <?= ($c['status'] ?? '') === 'ACTIVE' ? 'b-badge--verified' : 'b-badge--orange' ?>"><?= View::e($c['status']) ?></span>
              <?php if (($c['status'] ?? '') !== 'ACTIVE' && $gateD39Open): ?>
                <form action="/staff/campaigns/<?= (int) ($c['id'] ?? 0) ?>/approve" method="POST" style="display: inline;">
                  <?= View::csrfField() ?>
                  <button type="submit" class="b-btn b-btn--primary b-btn--sm" style="padding: 0.25rem 0.65rem; font-size: 0.72rem;">Approve</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
