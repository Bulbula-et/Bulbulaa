<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

/**
 * C-25 Pre-Publication Review Moderation Queue View.
 */
$reviews      = $reviews ?? [];
$statusFilter = $statusFilter ?? 'PENDING_MODERATION';
?>
<div style="margin-bottom: var(--space-4); display: flex; gap: 0.5rem;">
  <a href="/staff/moderation?status=PENDING_MODERATION" class="b-btn <?= $statusFilter === 'PENDING_MODERATION' ? 'b-btn--primary' : 'b-btn--outline' ?> b-btn--sm">Pending Moderation</a>
  <a href="/staff/moderation?status=PUBLISHED" class="b-btn <?= $statusFilter === 'PUBLISHED' ? 'b-btn--primary' : 'b-btn--outline' ?> b-btn--sm">Published</a>
  <a href="/staff/moderation?status=REJECTED" class="b-btn <?= $statusFilter === 'REJECTED' ? 'b-btn--primary' : 'b-btn--outline' ?> b-btn--sm">Rejected</a>
</div>

<div class="b-table-wrap">
  <table class="b-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Branch</th>
        <th>Customer Author</th>
        <th>Rating</th>
        <th>Review Text</th>
        <th>Status</th>
        <th>Moderation Decision (C-25)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($reviews as $rev): ?>
        <tr>
          <td class="b-tabular">#<?= (int) $rev['id'] ?></td>
          <td><strong><?= View::e($rev['branch_name_en']) ?></strong></td>
          <td><?= View::e($rev['author_name']) ?></td>
          <td><span class="b-rating b-tabular">&#9733; <?= (int) $rev['rating'] ?>/5</span></td>
          <td><?= View::e($rev['review_text'] ?? '(No written text)') ?></td>
          <td><span class="b-badge b-badge--orange"><?= View::e($rev['status']) ?></span></td>
          <td>
            <div style="display: flex; gap: 0.35rem;">
              <form method="POST" action="/staff/moderation/<?= (int) $rev['id'] ?>/decide">
                <?= Csrf::field() ?>
                <input type="hidden" name="decision" value="PUBLISHED">
                <input type="hidden" name="reason_code" value="POLICY_COMPLIANT">
                <button type="submit" class="b-btn b-btn--primary b-btn--sm">Approve &amp; Publish</button>
              </form>
              <form method="POST" action="/staff/moderation/<?= (int) $rev['id'] ?>/decide">
                <?= Csrf::field() ?>
                <input type="hidden" name="decision" value="REJECTED">
                <input type="hidden" name="reason_code" value="POLICY_VIOLATION">
                <button type="submit" class="b-btn b-btn--outline b-btn--sm">Reject</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
