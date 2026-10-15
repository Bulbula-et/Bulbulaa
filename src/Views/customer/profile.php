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

  <?php if (empty($customer['email_verified_at'])): ?>
    <section class="b-card" style="margin-bottom:var(--space-5);border-color:var(--orange-200);background:var(--orange-50)">
      <div><span class="b-badge b-badge--orange">Email verification required</span><h2 style="font-size:var(--text-lg);margin-top:var(--space-2)">Verify <?= View::e($customer['email'] ?? '') ?></h2><p style="font-size:var(--text-sm);color:var(--neutral-600)">Verification is required before saving places or publishing reviews.</p></div>
      <?php if (!empty($emailVerificationDevPreview)): ?><div class="b-alert b-alert--info">Preview verification code: <strong><?= View::e($emailVerificationDevPreview) ?></strong></div><?php endif; ?>
      <div style="display:flex;gap:var(--space-3);flex-wrap:wrap">
        <form method="POST" action="/account/email/verification/request"><?= Csrf::field() ?><button class="b-btn b-btn--outline b-btn--sm" type="submit">Send verification code</button></form>
        <form method="POST" action="/account/email/verification/verify" style="display:flex;gap:var(--space-2)"><?= Csrf::field() ?><input class="b-input" name="otp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Six-digit code" required style="min-height:36px"><button class="b-btn b-btn--primary b-btn--sm" type="submit">Verify email</button></form>
      </div>
    </section>
  <?php endif; ?>

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
    <section class="b-card">
      <h2 style="font-size:var(--text-lg);margin-bottom:var(--space-3)">Sign-in email</h2><p style="font-size:var(--text-sm);color:var(--neutral-600)"><?= View::e($customer['email'] ?? '') ?></p>
      <?php if(empty($emailChangeStage)): ?><form method="POST" action="/account/email/change" style="display:grid;gap:var(--space-3)"><?= Csrf::field() ?><div><label class="b-label" for="new-email">New email address</label><input id="new-email" class="b-input" type="email" name="new_email" autocomplete="email" required></div><?php if(!empty($customer['password_hash'])): ?><div><label class="b-label" for="email-password">Current password</label><input id="email-password" class="b-input" type="password" name="current_password" autocomplete="current-password" required></div><?php endif; ?><button class="b-btn b-btn--outline b-btn--sm" type="submit">Begin email change</button></form>
      <?php else: ?><div class="b-alert b-alert--info"><?php if(!empty($emailChangeDevPreview)): ?>Preview code: <strong><?= View::e($emailChangeDevPreview) ?></strong><br><?php endif; ?><?= $emailChangeStage==='CURRENT'?'Confirm the code sent to your current email.':'Confirm the code sent to '.View::e($emailChangeNewEmail).'.' ?></div><form method="POST" action="<?= $emailChangeStage==='CURRENT'?'/account/email/change/current':'/account/email/change/complete' ?>" style="display:flex;gap:var(--space-2)"><?= Csrf::field() ?><input class="b-input" name="otp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="Six-digit code" required><button class="b-btn b-btn--primary b-btn--sm" type="submit">Confirm</button></form><?php endif; ?>
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
      <?php if(!empty($pendingSensitiveAction)): ?>
      <form method="POST" action="/account/privacy-request/verify" style="display:flex;flex-direction:column;gap:.5rem"><?= Csrf::field() ?><?php if(!empty($sensitiveActionDevPreview)): ?><div class="b-alert b-alert--info">Preview code: <strong><?= View::e($sensitiveActionDevPreview) ?></strong></div><?php endif; ?><label class="b-label" for="privacy-code">Confirmation code for <?= View::e(ucwords(strtolower(str_replace('_',' ',$pendingSensitiveAction)))) ?></label><input id="privacy-code" class="b-input" name="otp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required><button type="submit" class="b-btn b-btn--primary b-btn--sm">Confirm and submit request</button></form>
      <?php else: ?><form method="POST" action="/account/privacy-request" style="display:flex;flex-direction:column;gap:.5rem"><?= Csrf::field() ?><select name="request_type" class="b-select" style="min-height:38px;font-size:var(--text-xs)"><option value="DATA_ACCESS">Request Account Data Summary</option><option value="ACCOUNT_DELETION">Request Account Deletion</option></select><button type="submit" class="b-btn b-btn--outline b-btn--sm">Email confirmation code</button></form><?php endif; ?>
    </section>

    <section class="b-card" style="align-items:flex-start">
      <div style="width:100%"><span class="b-badge b-badge--verified">Active sessions</span><h2 style="font-size:var(--text-xl);margin-top:var(--space-3)">Signed-in devices</h2><?php foreach($activeSessions as $device): ?><div style="display:flex;justify-content:space-between;gap:1rem;padding:.75rem 0;border-top:1px solid var(--neutral-100)"><div><strong><?= View::e($device['device_label']) ?></strong><div style="font-size:var(--text-xs);color:var(--neutral-500)"><?= !empty($device['is_current'])?'This device · ':'' ?>Last active <?= View::e($device['last_seen_at']) ?></div></div><?php if(empty($device['is_current'])): ?><form method="POST" action="/account/sessions/<?= (int)$device['id'] ?>/revoke"><?= Csrf::field() ?><button class="b-btn b-btn--outline b-btn--sm" type="submit">Sign out</button></form><?php endif; ?></div><?php endforeach; ?><form method="POST" action="/account/sessions/others/revoke" style="margin-top:var(--space-3)"><?= Csrf::field() ?><button class="b-btn b-btn--outline b-btn--sm" type="submit">Sign out all other devices</button></form></div>
    </section>

    <section class="b-card" style="display:block">
      <span class="b-badge">Security activity</span><h2 style="font-size:var(--text-xl);margin-top:var(--space-3)">Recent account events</h2>
      <?php if (empty($securityEvents)): ?><p style="font-size:var(--text-sm);color:var(--neutral-600)">No account security events have been recorded yet.</p><?php else: ?><ul style="list-style:none;padding:0;margin:var(--space-4) 0 0;display:grid;gap:var(--space-3)"><?php foreach ($securityEvents as $event): ?><li style="display:flex;justify-content:space-between;gap:var(--space-3);font-size:var(--text-sm);border-top:1px solid var(--neutral-100);padding-top:var(--space-3)"><strong><?= View::e(ucwords(strtolower(str_replace('_',' ',(string)$event['action_code'])))) ?></strong><time style="color:var(--neutral-500)"><?= View::e((string)$event['created_at']) ?></time></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
  </div>
</div>
