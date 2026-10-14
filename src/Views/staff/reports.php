<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$reports = $reports ?? [];
$open = 0;
$resolved = 0;
foreach ($reports as $report) {
    if (str_starts_with((string) ($report['workflow_status'] ?? ''), 'RESOLVED')) $resolved++; else $open++;
}
?>
<section class="b-report-summary" aria-label="Report summary">
  <article class="b-report-stat b-report-stat--all"><span class="b-report-stat__label">Reports received</span><strong class="b-report-stat__value"><?= count($reports) ?></strong><span class="b-report-stat__meta">Public accuracy and policy signals</span></article>
  <article class="b-report-stat b-report-stat--open"><span class="b-report-stat__label">Open for triage</span><strong class="b-report-stat__value"><?= $open ?></strong><span class="b-report-stat__meta">Requires an operational decision</span></article>
  <article class="b-report-stat b-report-stat--resolved"><span class="b-report-stat__label">Resolved</span><strong class="b-report-stat__value"><?= $resolved ?></strong><span class="b-report-stat__meta">Verified and closed reports</span></article>
</section>

<section class="b-card b-report-register">
  <header class="b-report-register__head"><div><h2>Public report triage register</h2><p>Investigate customer-submitted corrections and close them with a verified outcome.</p></div><span class="b-report-sla">Accuracy-control workspace</span></header>
  <?php if ($reports === []): ?>
    <div class="b-report-empty"><strong>No public reports</strong>The triage queue is clear. New customer reports will appear here.</div>
  <?php else: ?>
    <div class="b-report-list">
      <?php foreach ($reports as $rep): ?>
        <?php $isResolved = str_starts_with((string) $rep['workflow_status'], 'RESOLVED'); ?>
        <article class="b-report-item">
          <div>
            <div class="b-report-item__meta"><span class="b-report-id">Report #<?= (int) $rep['id'] ?></span><span>·</span><span class="b-report-target"><?= View::e($rep['branch_name_en'] ?? $rep['business_name_en'] ?? $rep['target_type']) ?></span><span class="b-badge b-badge--orange"><?= View::e(str_replace('_', ' ', $rep['issue_category'])) ?></span><span class="b-badge <?= $isResolved ? 'b-badge--verified' : 'b-badge--blue' ?>"><?= View::e(str_replace('_', ' ', $rep['workflow_status'])) ?></span></div>
            <p class="b-report-item__text"><?= View::e($rep['details_text']) ?></p>
          </div>
          <div class="b-report-item__action">
            <?php if ($isResolved): ?><span class="b-badge b-badge--verified">✓ Resolution recorded</span><?php else: ?>
              <form method="POST" action="/staff/reports/<?= (int) $rep['id'] ?>/resolve"><?= Csrf::field() ?><input type="hidden" name="workflow_status" value="RESOLVED_UPDATED"><button type="submit" class="b-btn b-btn--primary b-btn--sm">Verify and resolve</button></form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
