<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$returnTo     = $returnTo ?? '/account';
$pendingEmail = $pendingEmail ?? null;
$devOtp       = $devOtp ?? null;
?>
<div class="b-container" style="padding: var(--space-12) var(--space-5); max-width: 28rem;">
  <section class="b-card" style="padding: var(--space-8);">
    <div>
      <span class="b-badge b-badge--orange">Password-Free Access</span>
      <h1 style="font-size: var(--text-2xl); margin-top: var(--space-3);">Sign In to Bulbula.et</h1>
      <p style="font-size: var(--text-sm); color: var(--neutral-600); margin-top: 0.35rem;">
        Sign in to save places or write and manage reviews across Web and Telegram.
      </p>
    </div>

    <?php if (!empty($devOtp)): ?>
      <div style="background: var(--orange-50); border: 1px solid var(--orange-200); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); font-size: var(--text-xs);">
        <strong style="color: var(--orange-700);">Preview One-Time Code:</strong>
        <span class="b-tabular" style="font-family: var(--font-mono); font-size: var(--text-base); font-weight: 800; color: var(--neutral-900); margin-left: 0.4rem;">
          <?= View::e($devOtp) ?>
        </span>
      </div>
    <?php endif; ?>

    <?php if (empty($pendingEmail)): ?>
      <form method="POST" action="/auth/otp/request" style="display: flex; flex-direction: column; gap: var(--space-3);">
        <?= Csrf::field() ?>
        <input type="hidden" name="return_to" value="<?= View::e($returnTo) ?>">
        <div>
          <label class="b-label" for="otp-email">Email Address</label>
          <input id="otp-email" type="email" name="email" class="b-input" required value="abebe.kebede@example.com" placeholder="you@example.com">
        </div>
        <button type="submit" class="b-btn b-btn--primary">Send 6-Digit Code</button>
      </form>
    <?php else: ?>
      <form method="POST" action="/auth/otp/verify" style="display: flex; flex-direction: column; gap: var(--space-3);">
        <?= Csrf::field() ?>
        <input type="hidden" name="return_to" value="<?= View::e($returnTo) ?>">
        <input type="hidden" name="email" value="<?= View::e($pendingEmail) ?>">
        <div>
          <label class="b-label" for="otp-code">Enter 6-Digit Code Sent to <?= View::e($pendingEmail) ?></label>
          <input id="otp-code" type="text" name="otp_code" class="b-input b-tabular" maxlength="6" required value="<?= View::e($devOtp ?? '') ?>" placeholder="123456">
        </div>
        <button type="submit" class="b-btn b-btn--primary">Verify &amp; Sign In</button>
      </form>
    <?php endif; ?>

    <div style="text-align: center; font-size: var(--text-xs); color: var(--neutral-500); border-top: 1px solid var(--color-border-subtle); padding-top: var(--space-3);">
      OR
    </div>

    <form method="POST" action="/auth/google/callback">
      <?= Csrf::field() ?>
      <input type="hidden" name="return_to" value="<?= View::e($returnTo) ?>">
      <input type="hidden" name="google_email" value="liya.tadesse@example.com">
      <input type="hidden" name="google_name" value="Liya T.">
      <button type="submit" class="b-btn b-btn--outline" style="width: 100%;">
        <span>Continue with Google (Demo: Liya T.)</span>
      </button>
    </form>
  </section>
</div>
