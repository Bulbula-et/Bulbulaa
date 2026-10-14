<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\SecurityHeaders;
use Bulbula\Core\View;

$returnTo     = $returnTo ?? '/account';
$pendingEmail = $pendingEmail ?? null;
$devOtp       = $devOtp ?? null;
?>
<div class="b-container" style="padding: var(--space-12) var(--space-5); max-width: 28rem;">
  <section class="b-card" style="padding: var(--space-8);">
    <div>
      <span class="b-badge b-badge--orange">Secure customer access</span>
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
      <form method="POST" action="/auth/password/login" style="display:flex;flex-direction:column;gap:var(--space-3)">
        <?= Csrf::field() ?><input type="hidden" name="return_to" value="<?= View::e($returnTo) ?>">
        <div><label class="b-label" for="login-email">Email address</label><input id="login-email" type="email" name="email" class="b-input" autocomplete="email" placeholder="you@example.com" required></div>
        <div><div style="display:flex;justify-content:space-between;gap:var(--space-2)"><label class="b-label" for="login-password">Password</label><a href="/auth/forgot-password" style="font-size:var(--text-xs)">Forgot password?</a></div><input id="login-password" type="password" name="password" class="b-input" autocomplete="current-password" required></div>
        <button type="submit" class="b-btn b-btn--primary">Sign in</button>
      </form>
      <p style="font-size:var(--text-xs);text-align:center;margin:var(--space-3) 0">New to Bulbula.et? <a href="/auth/register">Create an account</a></p>
      <details style="border-top:1px solid var(--color-border-subtle);padding-top:var(--space-3)"><summary style="cursor:pointer;font-size:var(--text-xs);font-weight:700">Sign in with a one-time email code</summary>
      <form method="POST" action="/auth/otp/request" style="display:flex;flex-direction:column;gap:var(--space-3);margin-top:var(--space-3)">
        <?= Csrf::field() ?>
        <input type="hidden" name="return_to" value="<?= View::e($returnTo) ?>">
        <div>
          <label class="b-label" for="otp-email">Email Address</label>
          <input id="otp-email" type="email" name="email" class="b-input" required autocomplete="email" placeholder="you@example.com">
        </div>
        <button type="submit" class="b-btn b-btn--outline">Send one-time code</button>
      </form></details>
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

    <?php if ($googleClientId !== ''): ?>
      <form id="google-auth-form" method="POST" action="/auth/google/callback">
        <?= Csrf::field() ?>
        <input type="hidden" name="return_to" value="<?= View::e($returnTo) ?>">
        <input id="google-credential" type="hidden" name="credential" value="">
      </form>
      <div id="g_id_onload"
           data-client_id="<?= View::e($googleClientId) ?>"
           data-callback="bulbulaGoogleCredential"
           data-auto_prompt="false"
           data-ux_mode="popup"></div>
      <div class="g_id_signin" data-type="standard" data-shape="pill" data-theme="outline" data-text="continue_with" data-size="large" data-logo_alignment="left" data-width="360"></div>
      <script nonce="<?= SecurityHeaders::nonce() ?>" src="https://accounts.google.com/gsi/client" async defer></script>
      <script nonce="<?= SecurityHeaders::nonce() ?>">
        function bulbulaGoogleCredential(response) {
          if (!response || !response.credential) return;
          document.getElementById('google-credential').value = response.credential;
          document.getElementById('google-auth-form').submit();
        }
      </script>
    <?php else: ?>
      <div class="b-alert" style="font-size: var(--text-xs);">
        Google Sign-In will appear after an Administrator saves the Google OAuth Client ID under <strong>Staff Console → Auth &amp; SMTP Settings</strong>.
      </div>
    <?php endif; ?>
  </section>
</div>
