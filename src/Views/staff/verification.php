<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

/** C-21 Verification & Quality Control workspace. */
$branches = $branches ?? [];
$total = count($branches);
$verified = 0;
$pending = 0;
foreach ($branches as $branch) {
    if (!empty($branch['is_verified'])) $verified++;
    if (empty($branch['permission']) || (($branch['latest_qc']['outcome']??'') !== 'PASSED')) $pending++;
}
?>
<section class="b-qc-summary" aria-label="Verification summary">
  <article class="b-qc-stat">
    <span class="b-qc-stat__label">Directory branches</span>
    <strong class="b-qc-stat__value"><?= $total ?></strong>
    <span class="b-qc-stat__meta">In the controlled QC portfolio</span>
  </article>
  <article class="b-qc-stat b-qc-stat--good">
    <span class="b-qc-stat__label">Verified and trusted</span>
    <strong class="b-qc-stat__value"><?= $verified ?></strong>
    <span class="b-qc-stat__meta">Passed permission and quality gates</span>
  </article>
  <article class="b-qc-stat b-qc-stat--attention">
    <span class="b-qc-stat__label">Needs attention</span>
    <strong class="b-qc-stat__value"><?= $pending ?></strong>
    <span class="b-qc-stat__meta">Missing permission or pending QC</span>
  </article>
</section>

<section class="b-card b-qc-panel">
  <header class="b-qc-panel__header">
    <div><h2>Verification and quality-control register</h2><p>Validate authorization, branch accuracy, and publication readiness before trust is granted.</p></div>
    <span class="b-qc-policy">Permission and quality controls active</span>
  </header>

  <?php if ($branches === []): ?>
    <div class="b-qc-empty"><strong>No branches awaiting verification</strong>The QC register will populate when a branch enters the directory workflow.</div>
  <?php else: ?>
    <div class="b-table-wrap" style="border:0;border-radius:0;box-shadow:none">
      <table class="b-table b-qc-table">
        <thead><tr><th>Branch</th><th>Permission gate</th><th>Latest QC review</th><th>Trust status</th><th style="text-align:right">Action</th></tr></thead>
        <tbody>
        <?php foreach ($branches as $br): ?>
          <?php $perm = $br['permission'] ?? null; $qc = $br['latest_qc'] ?? null; ?>
          <tr>
            <td class="b-qc-branch"><strong><?= View::e($br['branch_name_en']) ?></strong><span><?= View::e($br['address_line_en']) ?></span><span>Prepared by <?= View::e((string)($br['preparer']['full_name']??'unattributed Staff')) ?></span></td>
            <td>
              <?php if ($perm): ?>
                <div class="b-qc-gate"><span class="b-qc-gate__icon">✓</span><div><span class="b-badge b-badge--verified"><?= View::e($perm['status']) ?></span><span class="b-qc-detail"><?= View::e(str_replace('_', ' ', $perm['permission_method'])) ?><br><?= View::e($perm['rep_full_name']) ?> · <?= View::e($perm['rep_role_title']) ?></span></div></div>
              <?php else: ?>
                <div class="b-qc-gate b-qc-gate--missing"><span class="b-qc-gate__icon">!</span><div><span class="b-badge b-badge--orange">Missing permission</span><span class="b-qc-detail">Authorization is required before publication.</span></div></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($qc): ?>
                <span class="b-badge b-badge--blue"><?= View::e($qc['outcome']) ?></span><span class="b-qc-detail">Reviewed by <?= View::e($qc['reviewer_name'] ?? 'QC Lead') ?></span>
              <?php else: ?><span class="b-badge">Pending QC</span><span class="b-qc-detail">No completed quality review.</span><?php endif; ?>
            </td>
            <td><?= View::component('trust-badge', ['isVerified' => !empty($br['is_verified'])]) ?></td>
            <td><?php if(!empty($br['self_review_blocked'])): ?><div class="b-qc-separation"><strong>Independent review required</strong><span>Another active Staff member must review this listing.</span></div><?php else: ?><details class="b-qc-review"><summary>Review listing</summary><form method="POST" action="/staff/verification/<?= (int)$br['branch_id'] ?>/qc" class="b-qc-action"><?= Csrf::field() ?><?php if((int)($br['preparer']['id']??0)===(int)($staff['id']??0)): ?><div class="b-qc-separation"><strong>Controlled self-review fallback</strong><span>No other active Staff reviewer is available. Explain this exception in the notes.</span></div><?php endif; ?><fieldset><legend>Required checks</legend><label><input type="checkbox" name="permission_verified" value="1"> Permission evidence and scope confirmed</label><label><input type="checkbox" name="coordinates_checked" value="1"> Address, map position, and branch context checked</label><label><input type="checkbox" name="category_checked" value="1"> Primary category and classification checked</label></fieldset><label><span>Decision</span><select name="outcome" class="b-select" required><option value="">Choose outcome</option><option value="PASSED">Pass and publish</option><option value="RETURNED_FOR_FIXES">Return for fixes</option><option value="REJECTED">Reject verification</option></select></label><label><span>Reviewer notes</span><textarea name="reviewer_notes" class="b-input" rows="3" maxlength="1000" placeholder="Required for rejection, return, or controlled self-review"></textarea></label><button type="submit" class="b-btn b-btn--primary b-btn--sm">Record QC decision</button></form></details><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
