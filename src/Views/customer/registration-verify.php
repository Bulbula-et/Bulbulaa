<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;
?>
<div class="b-container" style="padding:var(--space-12) var(--space-5);max-width:30rem">
  <section class="b-card" style="padding:var(--space-8)">
    <span class="b-badge b-badge--orange">One more step</span>
    <h1 style="font-size:var(--text-2xl);margin-top:var(--space-3)">Verify your email</h1>
    <p>We sent a six-digit code to <strong><?= View::e($pendingEmail??'') ?></strong>. Your account is not created until the code is verified. It expires in 10 minutes.</p>
    <?php if (!empty($devCode)): ?><p class="b-alert b-alert--info">Development preview code: <strong><?= View::e($devCode) ?></strong></p><?php endif; ?>
    <form method="POST" action="/auth/register/verify" style="display:grid;gap:var(--space-3);margin-top:var(--space-5)">
      <?= Csrf::field() ?>
      <label class="b-label" for="registration-code">Email verification code</label>
      <input id="registration-code" class="b-input" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="123456" required>
      <button class="b-btn b-btn--primary" type="submit">Verify email &amp; create account</button>
    </form>
    <form method="POST" action="/auth/register/resend" style="margin-top:var(--space-4)"><?= Csrf::field() ?><button class="b-btn b-btn--outline" type="submit">Send a new code</button></form>
    <p style="margin-top:var(--space-4)"><a href="/auth/register">Wrong email? Start again</a></p>
  </section>
</div>
