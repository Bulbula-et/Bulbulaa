<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;
use Bulbula\Domain\Policies\ReviewPolicy;

$reviews  = $reviews ?? [];
$customer = $customer ?? [];
?>
<div class="b-container" style="padding-top: var(--space-6);">
  <div class="b-section__header">
    <div>
      <div class="b-section__eyebrow">Customer Feedback</div>
      <h1>My Reviews (<?= count($reviews) ?>)</h1>
    </div>
    <a href="/account" class="b-btn b-btn--outline b-btn--sm">&larr; Account</a>
  </div>

  <?php if (!empty($reviews)): ?>
    <div style="display: flex; flex-direction: column; gap: var(--space-4);">
      <?php foreach ($reviews as $rev): ?>
        <?php $editCheck = ReviewPolicy::canCustomerEdit($rev, (int) ($customer['id'] ?? 0)); ?>
        <article class="b-card">
          <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
            <div>
              <a href="/b/<?= View::e($rev['business_slug']) ?>/<?= View::e($rev['branch_slug']) ?>" style="font-weight: 700; font-size: var(--text-lg); color: var(--neutral-900);">
                <?= View::e($rev['branch_name_en']) ?>
              </a>
              <span class="b-rating b-tabular" style="margin-left: 0.5rem;">
                <span class="b-rating__star">&#9733;</span> <?= (int) $rev['rating'] ?> / 5
              </span>
            </div>
            <span class="b-badge <?= $rev['status'] === 'PUBLISHED' ? 'b-badge--verified' : 'b-badge--orange' ?>">
              <?= View::e($rev['status']) ?>
            </span>
          </div>

          <p style="font-size: var(--text-sm); color: var(--neutral-800);">
            <?= View::e($rev['review_text'] ?? '(Rating only)') ?>
          </p>

          <div class="b-card__footer">
            <span style="font-size: var(--text-xs); color: var(--neutral-600);">
              Submitted <?= View::e($rev['first_submitted_at']) ?>
              <?php if ($editCheck['can_edit']): ?>
                &middot; <strong><?= (int) $editCheck['days_remaining'] ?> days left to edit</strong>
              <?php endif; ?>
            </span>

            <?php if (!in_array($rev['status'], ['WITHDRAWN_BY_AUTHOR', 'REMOVED_BY_STAFF'], true)): ?>
              <form method="POST" action="/account/reviews/<?= (int) $rev['id'] ?>/withdraw">
                <?= Csrf::field() ?>
                <button type="submit" class="b-btn b-btn--outline b-btn--sm">Withdraw</button>
              </form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="b-empty-state">
      <h3>No reviews submitted yet</h3>
      <div style="margin-top: var(--space-4);">
        <a href="/search" class="b-btn b-btn--primary">Find a Branch to Review</a>
      </div>
    </div>
  <?php endif; ?>
</div>
