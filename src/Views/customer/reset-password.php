<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
?>
<div class="b-container" style="padding:var(--space-12) var(--space-5);max-width:30rem"><section class="b-card" style="padding:var(--space-8)">
<span class="b-badge b-badge--verified">Email verified</span><h1 style="font-size:var(--text-2xl);margin-top:var(--space-3)">Choose a new password</h1><p style="font-size:var(--text-sm);color:var(--neutral-600);margin:.35rem 0 var(--space-5)">Use 12–128 characters with uppercase, lowercase, and a number.</p>
<form method="POST" action="/auth/reset-password" style="display:grid;gap:var(--space-3)" autocomplete="off"><?= Csrf::field() ?><div><label class="b-label" for="reset-password">New password</label><input id="reset-password" class="b-input" type="password" name="password" minlength="12" maxlength="128" autocomplete="new-password" required></div><div><label class="b-label" for="reset-confirm">Confirm new password</label><input id="reset-confirm" class="b-input" type="password" name="password_confirmation" minlength="12" maxlength="128" autocomplete="new-password" required></div><button class="b-btn b-btn--primary" type="submit">Reset password</button></form>
</section></div>
