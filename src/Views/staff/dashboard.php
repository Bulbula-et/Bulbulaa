<?php
declare(strict_types=1);
use Bulbula\Core\View;

/**
 * Staff Console Overview & Readiness Gates Dashboard (C-19..C-29, D-31, D-39, D-40, D-45).
 */
$summary  = $summary ?? [];
$totals   = $summary['totals'] ?? [];
$gates    = $gates ?? [];
$branches = $branches ?? [];
?>
<div class="b-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: var(--space-6);">
  <div class="b-card" style="padding: var(--space-4);">
    <span style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 700;">PUBLISHED BRANCHES</span>
    <div class="b-tabular" style="font-size: var(--text-2xl); font-weight: 800; color: var(--blue-700);"><?= (int) ($totals['published_branches'] ?? 0) ?></div>
    <span class="b-badge b-badge--verified"><?= (int) ($totals['verified_branches'] ?? 0) ?> Verified (C-12)</span>
  </div>

  <div class="b-card" style="padding: var(--space-4);">
    <span style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 700;">MODERATION QUEUE (C-25)</span>
    <div class="b-tabular" style="font-size: var(--text-2xl); font-weight: 800; color: var(--orange-600);"><?= (int) ($totals['pending_reviews'] ?? 0) ?></div>
    <a href="/staff/moderation" style="font-size: var(--text-xs); font-weight: 600;">Review pending items &rarr;</a>
  </div>

  <div class="b-card" style="padding: var(--space-4);">
    <span style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 700;">PUBLIC REPORTS (C-26)</span>
    <div class="b-tabular" style="font-size: var(--text-2xl); font-weight: 800; color: var(--blue-600);"><?= (int) ($totals['open_reports'] ?? 0) ?></div>
    <a href="/staff/reports" style="font-size: var(--text-xs); font-weight: 600;">Open triage queue &rarr;</a>
  </div>

  <div class="b-card" style="padding: var(--space-4);">
    <span style="font-size: var(--text-xs); color: var(--neutral-600); font-weight: 700;">SERVER-SIDE PROFILE VIEWS (C-38)</span>
    <div class="b-tabular" style="font-size: var(--text-2xl); font-weight: 800; color: var(--blue-700);"><?= (int) ($totals['total_profile_views'] ?? 0) ?></div>
    <span style="font-size: var(--text-xs); color: var(--neutral-600);"><?= (int) ($totals['total_contact_actions'] ?? 0) ?> Contact Actions</span>
  </div>
</div>

<!-- Readiness & Decision Gates Table (Page 14) -->
<section style="margin-bottom: var(--space-6);">
  <h2 style="font-size: var(--text-lg); margin-bottom: var(--space-3);">V1 Readiness &amp; Decision Gates (Page 14)</h2>
  <div class="b-table-wrap">
    <table class="b-table">
      <thead>
        <tr>
          <th>Decision Ref</th>
          <th>Gate Key</th>
          <th>Status</th>
          <th>Configuration / Scope Note</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($gates as $gate): ?>
          <tr>
            <td><span class="b-badge b-badge--blue"><?= View::e($gate['decision_ref']) ?></span></td>
            <td style="font-family: var(--font-mono); font-size: var(--text-xs); font-weight: 600;"><?= View::e($gate['gate_key']) ?></td>
            <td>
              <span class="b-badge <?= !empty($gate['is_enabled']) ? 'b-badge--verified' : 'b-badge--orange' ?>">
                <?= !empty($gate['is_enabled']) ? 'ENABLED' : 'GATED / PENDING' ?>
              </span>
            </td>
            <td style="font-size: var(--text-xs); color: var(--neutral-600);"><?= View::e($gate['config_json'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- Recent Directory Branches -->
<section>
  <h2 style="font-size: var(--text-lg); margin-bottom: var(--space-3);">Directory Branches &amp; Publication Status</h2>
  <div class="b-table-wrap">
    <table class="b-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Branch &amp; Business</th>
          <th>Category</th>
          <th>Area</th>
          <th>Status</th>
          <th>Trust (C-12)</th>
          <th>Rating (C-13)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($branches as $br): ?>
          <tr>
            <td class="b-tabular">#<?= (int) $br['branch_id'] ?></td>
            <td>
              <a href="/b/<?= View::e($br['business_slug']) ?>/<?= View::e($br['branch_slug']) ?>" style="font-weight: 700; color: var(--blue-700);">
                <?= View::e($br['branch_name_en']) ?>
              </a>
              <div style="font-size: var(--text-xs); color: var(--neutral-600);">Business: <?= View::e($br['business_name_en']) ?></div>
            </td>
            <td><?= View::e($br['primary_category_name_en']) ?></td>
            <td><?= View::e($br['area_name_en']) ?></td>
            <td><span class="b-badge b-badge--blue"><?= View::e($br['publication_status']) ?></span></td>
            <td><?= View::component('trust-badge', ['isVerified' => !empty($br['is_verified'])]) ?></td>
            <td class="b-tabular">
              <?= $br['published_rating_avg'] !== null ? ('&#9733; ' . number_format((float) $br['published_rating_avg'], 2) . ' (' . (int) $br['published_review_count'] . ')') : '—' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
