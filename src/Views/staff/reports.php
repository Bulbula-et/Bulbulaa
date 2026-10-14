<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$reports = $reports ?? [];
?>
<div class="b-table-wrap">
  <table class="b-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Target</th>
        <th>Issue Category</th>
        <th>Details Submitted</th>
        <th>Workflow Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($reports as $rep): ?>
        <tr>
          <td class="b-tabular">#<?= (int) $rep['id'] ?></td>
          <td>
            <strong><?= View::e($rep['branch_name_en'] ?? $rep['business_name_en'] ?? $rep['target_type']) ?></strong>
          </td>
          <td><span class="b-badge b-badge--orange"><?= View::e($rep['issue_category']) ?></span></td>
          <td><?= View::e($rep['details_text']) ?></td>
          <td><span class="b-badge b-badge--blue"><?= View::e($rep['workflow_status']) ?></span></td>
          <td>
            <form method="POST" action="/staff/reports/<?= (int) $rep['id'] ?>/resolve">
              <?= Csrf::field() ?>
              <input type="hidden" name="workflow_status" value="RESOLVED_UPDATED">
              <button type="submit" class="b-btn b-btn--primary b-btn--sm">Mark Verified &amp; Resolved</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
