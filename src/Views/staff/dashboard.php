<?php
declare(strict_types=1);
use Bulbula\Core\View;
$summary=$summary??[];$totals=$summary['totals']??[];$gates=$gates??[];$branches=$branches??[];
$gateLabels=[
  'D_31_OPERATIONAL_PILOT'=>'Operational pilot',
  'D_39_PAID_CAMPAIGNS_ENABLED'=>'Paid campaign approvals',
  'D_40_LAUNCH_AREA_BOUNDARY'=>'Launch area boundary',
  'D_45_STAFF_2FA_REQUIRED'=>'Staff two-factor authentication',
];
$gateDescriptions=[
  'D_31_OPERATIONAL_PILOT'=>'Controls the current operational rollout scope.',
  'D_39_PAID_CAMPAIGNS_ENABLED'=>'Allows approved sponsored campaigns to be activated.',
  'D_40_LAUNCH_AREA_BOUNDARY'=>'Restricts publication to the approved launch geography.',
  'D_45_STAFF_2FA_REQUIRED'=>'Requires authenticator protection for Staff access.',
];
$dailyActivity=$summary['daily_activity']??[];
$overviewTrend=[
  'type'=>'line',
  'labels'=>array_map(static fn(array $row):string=>date('D',strtotime((string)$row['date'])),$dailyActivity),
  'datasets'=>[
    ['label'=>'Profile views','data'=>array_column($dailyActivity,'views'),'color'=>'#397eb5','fill'=>true],
    ['label'=>'Contact actions','data'=>array_map(static fn(array $row):int=>(int)$row['calls']+(int)$row['directions']+(int)$row['websites']+(int)$row['emails'],$dailyActivity),'color'=>'#ea6a15'],
  ],
];
$workloadChart=[
  'type'=>'bar','indexAxis'=>'y','legend'=>false,
  'labels'=>['Pending reviews','Open reports','Draft or QC'],
  'datasets'=>[['label'=>'Items','data'=>[(int)($totals['pending_reviews']??0),(int)($totals['open_reports']??0),(int)($totals['draft_or_qc_branches']??0)],'color'=>'#ea6a15']],
];
?>
<section class="b-admin-welcome">
  <div><h2>Good to see you, <?= View::e(explode(' ', trim((string)($staff['full_name']??'Team')))[0]) ?>.</h2><p>Here is the current health of the Bulbula.et directory and its operational queues.</p></div>
  <div class="b-admin-welcome__actions"><a class="b-btn b-btn--outline b-btn--sm" href="/staff/analytics">View analytics</a><a class="b-btn b-btn--primary b-btn--sm" href="/staff/listings">Manage listings</a></div>
</section>

<div class="b-metric-grid" aria-label="Operational summary">
  <article class="b-metric is-green"><div class="b-metric__top"><span>Published branches</span><span>Live</span></div><div class="b-metric__value b-tabular"><?= (int)($totals['published_branches']??0) ?></div><div class="b-metric__meta"><?= (int)($totals['verified_branches']??0) ?> verified directory locations</div></article>
  <article class="b-metric is-orange"><div class="b-metric__top"><span>Moderation queue</span><span>Reviews</span></div><div class="b-metric__value b-tabular"><?= (int)($totals['pending_reviews']??0) ?></div><div class="b-metric__meta"><a href="/staff/moderation">Review pending items →</a></div></article>
  <article class="b-metric"><div class="b-metric__top"><span>Open reports</span><span>Triage</span></div><div class="b-metric__value b-tabular"><?= (int)($totals['open_reports']??0) ?></div><div class="b-metric__meta"><a href="/staff/reports">Open reports workspace →</a></div></article>
  <article class="b-metric"><div class="b-metric__top"><span>Profile views</span><span>All time</span></div><div class="b-metric__value b-tabular"><?= (int)($totals['total_profile_views']??0) ?></div><div class="b-metric__meta"><?= (int)($totals['total_contact_actions']??0) ?> tracked contact actions</div></article>
</div>

<section class="b-dashboard-charts" aria-label="Operational charts">
  <article class="b-panel b-dashboard-chart"><header class="b-panel__header"><div><h2>Directory activity</h2><p>Discovery and high-intent actions from the last 7 days</p></div><span class="b-badge b-badge--blue">7 days</span></header><div class="b-dashboard-chart__frame"><canvas data-bulbula-chart data-chart-config="<?= View::e((string)json_encode($overviewTrend)) ?>" role="img" aria-label="Seven-day line chart of profile views and contact actions">Seven-day directory activity trend.</canvas></div></article>
  <article class="b-panel b-dashboard-chart"><header class="b-panel__header"><div><h2>Current workload</h2><p>Items requiring operational attention</p></div></header><div class="b-dashboard-chart__frame"><canvas data-bulbula-chart data-chart-config="<?= View::e((string)json_encode($workloadChart)) ?>" role="img" aria-label="Horizontal bar chart of pending operational work">Pending reviews, open reports, and branches awaiting quality control.</canvas></div></article>
</section>

<div class="b-dashboard-grid">
  <section class="b-panel">
    <header class="b-panel__header"><div><h2>Platform readiness controls</h2><p>Administrator-managed launch and policy gates</p></div><span class="b-badge b-badge--blue"><?= count($gates) ?> controls</span></header>
    <div class="b-panel__body">
      <?php foreach($gates as $gate): ?>
        <div class="b-gate-row">
          <i class="b-gate-row__state <?= !empty($gate['is_enabled'])?'is-on':'' ?>"></i>
          <?php $gateKey=(string)$gate['gate_key']; ?>
          <div class="b-gate-row__info"><strong><?= View::e($gateLabels[$gateKey]??ucwords(strtolower(str_replace('_',' ',(string)preg_replace('/^[A-Z]_[0-9]+_/','',$gateKey))))) ?></strong><span><?= View::e($gateDescriptions[$gateKey]??'Administrator-managed platform readiness control.') ?></span></div>
          <span class="b-badge <?= !empty($gate['is_enabled'])?'b-badge--verified':'b-badge--orange' ?>"><?= !empty($gate['is_enabled'])?'Enabled':'Pending' ?></span>
          <form action="/staff/gates/toggle" method="POST"><?= View::csrfField() ?><input type="hidden" name="gate_key" value="<?= View::e($gate['gate_key']) ?>"><input type="hidden" name="new_state" value="<?= !empty($gate['is_enabled'])?'0':'1' ?>"><button type="submit" class="b-btn b-btn--outline b-btn--sm b-gate-toggle"><?= !empty($gate['is_enabled'])?'Disable':'Enable' ?></button></form>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <aside class="b-panel">
    <header class="b-panel__header"><div><h2>Priority workspaces</h2><p>Continue where attention is needed</p></div></header>
    <div class="b-quick-list">
      <a class="b-quick-link" href="/staff/verification"><span>Verification queue</span><b>→</b></a>
      <a class="b-quick-link" href="/staff/moderation"><span>Review moderation</span><b><?= (int)($totals['pending_reviews']??0) ?></b></a>
      <a class="b-quick-link" href="/staff/reports"><span>Public reports</span><b><?= (int)($totals['open_reports']??0) ?></b></a>
      <a class="b-quick-link" href="/staff/campaigns"><span>Campaign approvals</span><b>→</b></a>
      <a class="b-quick-link" href="/staff/audit-logs"><span>Security audit trail</span><b>→</b></a>
    </div>
  </aside>
</div>

<section class="b-panel">
  <header class="b-panel__header"><div><h2>Directory portfolio</h2><p>Latest branch publication, trust, and customer rating status</p></div><a href="/staff/listings" class="b-btn b-btn--outline b-btn--sm">View all listings</a></header>
  <div class="b-table-wrap" style="border:0;border-radius:0;box-shadow:none"><table class="b-table"><thead><tr><th>Branch</th><th>Category</th><th>Area</th><th>Publication</th><th>Trust</th><th>Rating</th></tr></thead><tbody>
  <?php foreach($branches as $br): ?><tr><td><a href="/b/<?= View::e($br['business_slug']) ?>/<?= View::e($br['branch_slug']) ?>" style="font-weight:700"><?= View::e($br['branch_name_en']) ?></a><div style="font-size:10px;color:#9098a5;margin-top:3px"><?= View::e($br['business_name_en']) ?> · #<?= (int)$br['branch_id'] ?></div></td><td><?= View::e($br['primary_category_name_en']) ?></td><td><?= View::e($br['area_name_en']) ?></td><td><span class="b-badge b-badge--blue"><?= View::e($br['publication_status']) ?></span></td><td><?= View::component('trust-badge',['isVerified'=>!empty($br['is_verified'])]) ?></td><td class="b-tabular"><?= $br['published_rating_avg']!==null?'★ '.number_format((float)$br['published_rating_avg'],1).' · '.(int)$br['published_review_count']:'—' ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
