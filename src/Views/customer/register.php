<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
?>
<div class="b-container" style="padding:var(--space-12) var(--space-5);max-width:32rem">
  <section class="b-card" style="padding:var(--space-8)">
    <span class="b-badge b-badge--orange">Personal account</span>
    <h1 style="font-size:var(--text-2xl);margin-top:var(--space-3)">Create your Bulbula.et account</h1>
    <p style="font-size:var(--text-sm);color:var(--neutral-600);margin:.35rem 0 var(--space-5)">Save trusted places, publish reviews, and manage your personal information securely.</p>
    <form method="POST" action="/auth/register" style="display:grid;gap:var(--space-3)" autocomplete="on">
      <?= Csrf::field() ?>
      <div><label class="b-label" for="reg-name">Full name</label><input id="reg-name" class="b-input" type="text" name="display_name" autocomplete="name" maxlength="100" required></div>
      <div><label class="b-label" for="reg-email">Email address</label><input id="reg-email" class="b-input" type="email" name="email" autocomplete="email" placeholder="you@example.com" required></div>
      <div><label class="b-label" for="reg-password">Password</label><input id="reg-password" class="b-input" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="128" required><small style="color:var(--neutral-600)">Use at least 12 characters with uppercase, lowercase, and a number.</small></div>
      <div><label class="b-label" for="reg-confirm">Confirm password</label><input id="reg-confirm" class="b-input" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="128" required></div>
      <button class="b-btn b-btn--primary" type="submit">Create account</button>
    </form>
    <p style="margin-top:var(--space-4);font-size:var(--text-xs);text-align:center">Already registered? <a href="/auth/login">Sign in</a></p>
  </section>
</div>
