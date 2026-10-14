<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

/** C-25 Pre-Publication Review Moderation Queue. */
$reviews = $reviews ?? [];
$statusFilter = $statusFilter ?? 'PENDING_MODERATION';
$labels = ['PENDING_MODERATION' => 'Pending', 'PUBLISHED' => 'Published', 'REJECTED' => 'Rejected'];
$currentLabel = $labels[$statusFilter] ?? 'Reviews';
?>
<section class="b-mod-toolbar">
  <div class="b-mod-toolbar__title"><strong><?= View::e($currentLabel) ?> review queue · <?= count($reviews) ?></strong><span>Apply the C-25 policy gate before customer content reaches the public directory.</span></div>
  <nav class="b-mod-tabs" aria-label="Review status filters">
    <?php foreach ($labels as $status => $label): ?>
      <a href="/staff/moderation?status=<?= View::e($status) ?>" class="b-mod-tab <?= $statusFilter === $status ? 'is-active' : '' ?>" <?= $statusFilter === $status ? 'aria-current="page"' : '' ?>><?= View::e($label) ?></a>
    <?php endforeach; ?>
  </nav>
</section>

<?php if ($reviews === []): ?>
  <div class="b-mod-empty"><strong>No <?= View::e(strtolower($currentLabel)) ?> reviews</strong>This queue is clear. New customer submissions will appear here automatically.</div>
<?php else: ?>
  <section class="b-mod-list" aria-label="<?= View::e($currentLabel) ?> reviews">
    <?php foreach ($reviews as $rev): ?>
      <article class="b-mod-item">
        <div>
          <div class="b-mod-item__meta"><span class="b-mod-item__id">Review #<?= (int) $rev['id'] ?></span><span>·</span><span class="b-mod-item__branch"><?= View::e($rev['branch_name_en']) ?></span><span class="b-badge <?= $rev['status'] === 'PUBLISHED' ? 'b-badge--verified' : ($rev['status'] === 'REJECTED' ? '' : 'b-badge--orange') ?>"><?= View::e(str_replace('_', ' ', $rev['status'])) ?></span></div>
          <p class="b-mod-item__review"><?= View::e($rev['review_text'] ?? '(No written review was submitted.)') ?></p>
          <div class="b-mod-item__author"><span><?= View::e($rev['author_name']) ?></span><span>·</span><span class="b-mod-rating">★ <?= (int) $rev['rating'] ?>/5</span></div>
        </div>
        <div class="b-mod-actions">
          <form method="POST" action="/staff/moderation/<?= (int) $rev['id'] ?>/decide"><?= Csrf::field() ?><input type="hidden" name="decision" value="PUBLISHED"><input type="hidden" name="reason_code" value="POLICY_COMPLIANT"><button type="submit" class="b-btn b-btn--primary b-btn--sm">Approve &amp; publish</button></form>
          <form method="POST" action="/staff/moderation/<?= (int) $rev['id'] ?>/decide"><?= Csrf::field() ?><input type="hidden" name="decision" value="REJECTED"><input type="hidden" name="reason_code" value="POLICY_VIOLATION"><button type="submit" class="b-btn b-btn--outline b-btn--sm">Reject</button></form>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
<?php endif; ?>
