<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$staffPending = $staffPending ?? [];
$enrollment   = $enrollment ?? null;
?>
<div class="b-container" style="padding: var(--space-10) var(--space-5); max-width: 34rem;">
  <section class="b-card" style="padding: var(--space-8); border-top: 4px solid var(--orange-500);">
    <div style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
      <span class="b-badge b-badge--blue">Protected staff access</span>
      <span class="b-badge b-badge--verified">RFC 6238 TOTP</span>
    </div>
    <h1 style="font-size:var(--text-2xl);margin-top:var(--space-3);">
      <?= $enrollment !== null ? 'Set Up Authenticator' : 'Two-Factor Verification' ?>
    </h1>
    <p style="font-size:var(--text-sm);color:var(--neutral-600);margin-top:.35rem;">
      <?= View::e((string) ($staffPending['full_name'] ?? 'Staff')) ?> · <?= View::e((string) ($staffPending['email'] ?? '')) ?>
    </p>

    <?php if (is_array($enrollment)): ?>
      <div style="margin-top:var(--space-5);background:var(--orange-50);border:1px solid var(--orange-200);padding:var(--space-4);border-radius:var(--radius-md);">
        <strong>One-time enrollment</strong>
        <ol style="margin:.6rem 0 0 1.2rem;font-size:var(--text-sm);line-height:1.7;">
          <li>Open Google Authenticator, Microsoft Authenticator, 1Password, or another TOTP application.</li>
          <li>Choose <em>Enter setup key</em>.</li>
          <li>Account: <code><?= View::e((string) ($staffPending['email'] ?? '')) ?></code></li>
          <li>Key: <code style="font-size:var(--text-base);font-weight:800;letter-spacing:.08em;word-break:break-all;"><?= View::e((string) $enrollment['secret']) ?></code></li>
          <li>Type: Time based.</li>
        </ol>
        <details style="margin-top:.7rem;font-size:var(--text-xs);">
          <summary>Advanced provisioning URI</summary>
          <code style="word-break:break-all;display:block;margin-top:.35rem;"><?= View::e((string) $enrollment['uri']) ?></code>
        </details>
      </div>
    <?php endif; ?>

    <form method="POST" action="/staff/2fa" style="display:flex;flex-direction:column;gap:var(--space-3);margin-top:var(--space-5);">
      <?= Csrf::field() ?>
      <div>
        <label class="b-label" for="totp-code">6-Digit Authenticator Code</label>
        <input id="totp-code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" name="totp_code" class="b-input b-tabular" placeholder="123456" required autofocus style="font-size:var(--text-xl);letter-spacing:.25em;text-align:center;">
      </div>
      <button type="submit" class="b-btn b-btn--primary">Verify &amp; Enter Staff Console</button>
    </form>

    <p style="font-size:var(--text-xs);color:var(--neutral-500);margin-top:var(--space-4);">
      Codes rotate every 30 seconds. The setup key is encrypted before database storage and is never shown again after enrollment.
    </p>
  </section>
</div>
