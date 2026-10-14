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

  <div class="b-grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr));margin-bottom:var(--space-6)">
    <section class="b-card">
      <h2 style="font-size:var(--text-lg);margin-bottom:var(--space-3)">Personal profile</h2>
      <form method="POST" action="/account/profile" style="display:grid;gap:var(--space-3)">
        <?= Csrf::field() ?>
        <div><label class="b-label" for="profile-name">Full name</label><input id="profile-name" class="b-input" name="display_name" value="<?= View::e($customer['display_name'] ?? '') ?>" maxlength="100" required></div>
        <div><label class="b-label" for="profile-phone">Phone number</label><input id="profile-phone" class="b-input" name="phone_number" value="<?= View::e($customer['phone_number'] ?? '') ?>" autocomplete="tel" placeholder="+251 9..."></div>
        <div><label class="b-label" for="profile-language">Preferred language</label><select id="profile-language" class="b-select" name="preferred_language"><option value="en" <?= ($customer['preferred_language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option><option value="am" <?= ($customer['preferred_language'] ?? '') === 'am' ? 'selected' : '' ?>>Amharic</option></select></div>
        <button class="b-btn b-btn--primary b-btn--sm" type="submit">Save profile</button>
      </form>
    </section>
    <section class="b-card">
      <h2 style="font-size:var(--text-lg);margin-bottom:var(--space-3)"><?= !empty($customer['password_hash']) ? 'Change password' : 'Create a password' ?></h2>
      <form method="POST" action="/account/password" style="display:grid;gap:var(--space-3)" autocomplete="off">
        <?= Csrf::field() ?>
        <?php if (!empty($customer['password_hash'])): ?><div><label class="b-label" for="current-password">Current password</label><input id="current-password" class="b-input" type="password" name="current_password" autocomplete="current-password" required></div><?php else: ?><input type="hidden" name="current_password" value=""><?php endif; ?>
        <div><label class="b-label" for="new-password">New password</label><input id="new-password" class="b-input" type="password" name="new_password" minlength="12" maxlength="128" autocomplete="new-password" required></div>
        <div><label class="b-label" for="confirm-password">Confirm new password</label><input id="confirm-password" class="b-input" type="password" name="new_password_confirmation" minlength="12" maxlength="128" autocomplete="new-password" required></div>
        <button class="b-btn b-btn--outline b-btn--sm" type="submit">Save password</button>
      </form>
    </section>
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
