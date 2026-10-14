<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$campaigns = $campaigns ?? [];
$branches = $branches ?? [];
$gateD39Open = !empty($gateD39Open);
$activeCampaigns = 0;
$totalImpressions = 0;
$totalClicks = 0;
foreach ($campaigns as $campaign) {
    if (($campaign['status'] ?? '') === 'ACTIVE') $activeCampaigns++;
    $totalImpressions += (int) ($campaign['total_impressions'] ?? 0);
    $totalClicks += (int) ($campaign['total_clicks'] ?? 0);
}
?>
<section class="b-campaign-policy">
  <div class="b-campaign-policy__main">
    <h2>Commercial integrity boundary</h2>
    <p>Sponsored placements are intentionally separated from directory verification and organic ranking. Every campaign remains fixed-price, time-bounded, and transparently measured.</p>
    <div class="b-campaign-rules"><span class="b-campaign-rule">3 approved formats only</span><span class="b-campaign-rule">Impressions and clicks only</span><span class="b-campaign-rule">No organic ranking influence</span><span class="b-campaign-rule">Controlled approval workflow</span></div>
  </div>
  <aside class="b-campaign-gate <?= $gateD39Open ? '' : 'is-blocked' ?>"><span class="b-campaign-gate__label">Campaign approvals</span><strong class="b-campaign-gate__state"><?= $gateD39Open ? 'Active' : 'Blocked' ?></strong><span class="b-campaign-gate__meta"><?= $gateD39Open ? 'Campaign approval controls are available.' : 'Campaign creation may be recorded, but activation remains restricted.' ?></span></aside>
</section>

<section class="b-card b-campaign-create">
  <header class="b-campaign-create__head"><h2>Record a sponsored placement</h2><p>Create the commercial record before review and approval.</p></header>
  <form action="/staff/campaigns/create" method="POST" class="b-campaign-form">
    <?= Csrf::field() ?>
    <div><label class="b-label" for="cmp-branch">Published branch</label><select id="cmp-branch" name="branch_id" class="b-select" required><?php foreach ($branches as $br): ?><option value="<?= (int) $br['branch_id'] ?>"><?= View::e($br['branch_name_en']) ?></option><?php endforeach; ?></select></div>
    <div><label class="b-label" for="cmp-format">Placement format</label><select id="cmp-format" name="placement_format" class="b-select"><option value="HOMEPAGE_PROMOTION">Homepage promotion</option><option value="CATEGORY_SPONSORSHIP">Category sponsorship</option><option value="SEARCH_PLACEMENT">Search placement</option></select></div>
    <div><label class="b-label" for="cmp-price">Fixed price (ETB)</label><input id="cmp-price" type="number" step="0.01" name="agreed_price_etb" class="b-input" value="4500.00" min="0" required></div>
    <div><label class="b-label" for="cmp-days">Duration</label><input id="cmp-days" type="number" name="duration_days" class="b-input" value="30" min="1" max="365" required></div>
    <div><button type="submit" class="b-btn b-btn--primary b-btn--sm">Record campaign</button></div>
  </form>
</section>

<section class="b-card b-campaign-register">
  <header class="b-campaign-register__head"><h2>Campaign register</h2><span><?= count($campaigns) ?> campaigns · <?= $activeCampaigns ?> active · <?= number_format($totalImpressions) ?> impressions · <?= number_format($totalClicks) ?> clicks</span></header>
  <?php if ($campaigns === []): ?>
    <div class="b-campaign-empty"><strong>No campaign records</strong>Recorded placements will appear here for review and activation.</div>
  <?php else: ?>
    <div class="b-table-wrap" style="border:0;border-radius:0;box-shadow:none"><table class="b-table b-campaign-table"><thead><tr><th>Campaign</th><th>Branch</th><th>Placement</th><th>Target</th><th>Period</th><th>Fixed price</th><th>Delivery</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($campaigns as $c): ?>
        <tr>
          <td><span class="b-campaign-code"><?= View::e($c['campaign_code']) ?></span></td>
          <td class="b-campaign-branch"><strong><?= View::e($c['branch_name_en']) ?></strong></td>
          <td><span class="b-badge b-badge--orange"><?= View::e(str_replace('_', ' ', $c['placement_format'])) ?></span></td>
          <td><?= View::e($c['target_category_name'] ?? 'Homepage') ?><?= !empty($c['target_area_name']) ? ' · ' . View::e($c['target_area_name']) : '' ?></td>
          <td><span class="b-campaign-period"><?= View::e(substr((string) $c['starts_at'], 0, 10)) ?><br>to <?= View::e(substr((string) $c['ends_at'], 0, 10)) ?></span></td>
          <td class="b-tabular"><strong><?= number_format((float) $c['agreed_price_etb'], 2) ?></strong><br><span style="font-size:9px;color:#7b8998">ETB</span></td>
          <td><div class="b-campaign-metrics"><span><strong><?= number_format((int) $c['total_impressions']) ?></strong>Impressions</span><span><strong><?= number_format((int) $c['total_clicks']) ?></strong>Clicks</span></div></td>
          <td><div class="b-campaign-state"><span class="b-badge <?= ($c['status'] ?? '') === 'ACTIVE' ? 'b-badge--verified' : 'b-badge--orange' ?>"><?= View::e($c['status']) ?></span><?php if (($c['status'] ?? '') !== 'ACTIVE' && $gateD39Open): ?><form action="/staff/campaigns/<?= (int) ($c['id'] ?? 0) ?>/approve" method="POST"><?= Csrf::field() ?><button type="submit" class="b-btn b-btn--primary b-btn--sm">Approve</button></form><?php endif; ?></div></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
