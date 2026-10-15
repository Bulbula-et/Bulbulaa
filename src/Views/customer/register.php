<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;
$oldName=$oldName??'';$oldEmail=$oldEmail??'';
?>
<div class="b-container" style="padding:var(--space-12) var(--space-5);max-width:32rem">
  <section class="b-card" style="padding:var(--space-8)">
    <span class="b-badge b-badge--orange">Personal account</span>
    <h1 style="font-size:var(--text-2xl);margin-top:var(--space-3)">Create your Bulbula.et account</h1>
    <p style="font-size:var(--text-sm);color:var(--neutral-600);margin:.35rem 0 var(--space-5)">Save trusted places and publish reviews. Your account is created only after you verify your email with a six-digit code.</p>
    <form method="POST" action="/auth/register" style="display:grid;gap:var(--space-3)" autocomplete="on" data-customer-password-form data-registration-form>
      <?= Csrf::field() ?>
      <div><label class="b-label" for="reg-name">Full name</label><input id="reg-name" class="b-input" type="text" name="display_name" autocomplete="name" minlength="2" maxlength="100" value="<?= View::e($oldName) ?>" aria-describedby="reg-name-error" required><small id="reg-name-error" class="b-field-feedback" aria-live="polite"></small></div>
      <div><label class="b-label" for="reg-email">Email address</label><input id="reg-email" class="b-input" type="email" name="email" autocomplete="email" placeholder="you@example.com" value="<?= View::e($oldEmail) ?>" aria-describedby="reg-email-error" required><small id="reg-email-error" class="b-field-feedback" aria-live="polite"></small></div>
      <div><label class="b-label" for="reg-password">Password</label><input id="reg-password" class="b-input" type="password" name="password" autocomplete="new-password" minlength="8" maxlength="128" aria-describedby="reg-password-help reg-password-error" data-password required><small id="reg-password-help" class="b-field-feedback">At least 8 characters. Any type of character is allowed; a longer, unique phrase is safer.</small><small id="reg-password-error" class="b-field-feedback" aria-live="polite"></small><div class="b-password-meter" data-strength hidden><meter min="0" max="4" value="0" aria-label="Password strength"></meter><span data-strength-label aria-live="polite"></span></div><button type="button" class="b-password-reveal" data-password-reveal aria-controls="reg-password" aria-pressed="false">Show password</button></div>
      <div><label class="b-label" for="reg-confirm">Confirm password</label><input id="reg-confirm" class="b-input" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" maxlength="128" aria-describedby="reg-confirm-error" data-confirm required><small id="reg-confirm-error" class="b-field-feedback" aria-live="polite"></small></div>
      <button class="b-btn b-btn--primary" type="submit" data-submit>Send verification code</button>
      <p class="b-field-feedback" data-form-status role="status" aria-live="polite"></p>
    </form>
    <?php if (($googleClientId??'') !== ''): ?>
      <div style="text-align:center;color:var(--neutral-500);font-size:var(--text-xs);padding:var(--space-4) 0">OR</div>
      <?= View::component('customer-google-signin', ['googleClientId'=>$googleClientId, 'returnTo'=>'/account']) ?>
      <p class="b-field-feedback">Google verifies your identity separately; you do not need an email code for Google sign-in.</p>
    <?php endif; ?>
    <p style="margin-top:var(--space-4);font-size:var(--text-xs);text-align:center">Already registered? <a href="/auth/login">Sign in</a></p>
  </section>
</div>
