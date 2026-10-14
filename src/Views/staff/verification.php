<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

/**
 * C-21 Verification & Quality Control Gate Table.
 */
$branches = $branches ?? [];
?>
<div class="b-table-wrap">
  <table class="b-table">
    <thead>
      <tr>
        <th>Branch</th>
        <th>Permission Record (C-19 Hard Gate)</th>
        <th>Latest QC Review (C-21)</th>
        <th>Current Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($branches as $br): ?>
        <?php
          $perm = $br['permission'] ?? null;
          $qc   = $br['latest_qc'] ?? null;
        ?>
        <tr>
          <td>
            <strong><?= View::e($br['branch_name_en']) ?></strong>
            <div style="font-size: var(--text-xs); color: var(--neutral-600);"><?= View::e($br['address_line_en']) ?></div>
          </td>
          <td>
            <?php if ($perm): ?>
              <span class="b-badge b-badge--verified"><?= View::e($perm['status']) ?> (<?= View::e($perm['permission_method']) ?>)</span>
              <div style="font-size: var(--text-xs); color: var(--neutral-600); margin-top: 0.2rem;">
                Rep: <?= View::e($perm['rep_full_name']) ?> (<?= View::e($perm['rep_role_title']) ?>)
              </div>
            <?php else: ?>
              <span class="b-badge b-badge--orange">MISSING PERMISSION</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($qc): ?>
              <span class="b-badge b-badge--blue"><?= View::e($qc['outcome']) ?></span>
              <div style="font-size: var(--text-xs); color: var(--neutral-600); margin-top: 0.2rem;">
                By: <?= View::e($qc['reviewer_name'] ?? 'QC Lead') ?>
              </div>
            <?php else: ?>
              <span style="font-size: var(--text-xs); color: var(--neutral-500);">Pending QC</span>
            <?php endif; ?>
          </td>
          <td>
            <?= View::component('trust-badge', ['isVerified' => !empty($br['is_verified'])]) ?>
          </td>
          <td>
            <form method="POST" action="/staff/verification/<?= (int) $br['branch_id'] ?>/qc" style="display: flex; gap: 0.35rem;">
              <?= Csrf::field() ?>
              <input type="hidden" name="outcome" value="PASSED">
              <button type="submit" class="b-btn b-btn--primary b-btn--sm">Re-Verify &amp; Publish</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
