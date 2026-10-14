<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$customer   = $customer ?? [];
$savedItems = $savedItems ?? [];
$myReviews  = $myReviews ?? [];
?>
<div class="b-container" style="padding-top: var(--space-6);">
  <div class="b-section__header">
    <div>
      <div class="b-section__eyebrow">Customer Account</div>
      <h1><?= View::e($customer['display_name'] ?? 'Customer') ?></h1>
      <p style="font-size: var(--text-sm); color: var(--neutral-600);"><?= View::e($customer['email'] ?? '') ?></p>
    </div>

    <form method="POST" action="/auth/logout">
      <?= Csrf::field() ?>
      <button type="submit" class="b-btn b-btn--outline b-btn--sm">Sign Out</button>
    </form>
  </div>

  <div class="b-grid b-grid--branches">
    <section class="b-card">
      <div>
        <span class="b-badge b-badge--orange">Saved Places</span>
        <h2 style="font-size: var(--text-xl); margin-top: var(--space-3);">Saved List (<?= count($savedItems) ?>)</h2>
        <p style="font-size: var(--text-sm); color: var(--neutral-600); margin-top: 0.25rem;">
          Quickly access your bookmarked branches across Web and Telegram.
        </p>
      </div>
      <a href="/account/saved" class="b-btn b-btn--primary b-btn--sm">View Saved Places &rarr;</a>
    </section>

    <section class="b-card">
      <div>
        <span class="b-badge b-badge--orange">Reviews</span>
        <h2 style="font-size: var(--text-xl); margin-top: var(--space-3);">My Reviews (<?= count($myReviews) ?>)</h2>
        <p style="font-size: var(--text-sm); color: var(--neutral-600); margin-top: 0.25rem;">
          Check moderation status, edit within 30 days, or withdraw your reviews.
        </p>
      </div>
      <a href="/account/reviews" class="b-btn b-btn--outline b-btn--sm">Manage Reviews &rarr;</a>
    </section>

    <section class="b-card">
      <div>
        <span class="b-badge" style="background: var(--neutral-100); color: var(--neutral-600);">Privacy Controls</span>
        <h2 style="font-size: var(--text-xl); margin-top: var(--space-3);">Account &amp; Data Rights</h2>
        <p style="font-size: var(--text-xs); color: var(--neutral-600); margin-top: 0.25rem;">
          Request a copy of your account data or request account deletion.
        </p>
      </div>
      <form method="POST" action="/account/privacy-request" style="display: flex; flex-direction: column; gap: 0.5rem;">
        <?= Csrf::field() ?>
        <select name="request_type" class="b-select" style="min-height: 38px; font-size: var(--text-xs);">
          <option value="DATA_ACCESS">Request Account Data Summary</option>
          <option value="ACCOUNT_DELETION">Request Account Deletion</option>
        </select>
        <button type="submit" class="b-btn b-btn--outline b-btn--sm">Submit Request</button>
      </form>
    </section>
  </div>
</div>
