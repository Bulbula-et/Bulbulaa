<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;
$pendingEmail=$pendingEmail??null;$devOtp=$devOtp??null;
?>
<div class="b-container" style="padding:var(--space-12) var(--space-5);max-width:30rem"><section class="b-card" style="padding:var(--space-8)">
<span class="b-badge b-badge--orange">Account recovery</span><h1 style="font-size:var(--text-2xl);margin-top:var(--space-3)">Reset your password</h1><p style="font-size:var(--text-sm);color:var(--neutral-600);margin:.35rem 0 var(--space-5)">We will verify access to your email before allowing a password change.</p>
<?php if($pendingEmail===null): ?><form method="POST" action="/auth/forgot-password" style="display:grid;gap:var(--space-3)"><?= Csrf::field() ?><div><label class="b-label" for="recovery-email">Email address</label><input id="recovery-email" class="b-input" type="email" name="email" autocomplete="email" required></div><button class="b-btn b-btn--primary" type="submit">Send recovery code</button></form>
<?php else: ?><?php if($devOtp): ?><div class="b-alert b-alert--info" style="margin-bottom:var(--space-3)">Preview recovery code: <strong><?= View::e($devOtp) ?></strong></div><?php endif; ?><form method="POST" action="/auth/forgot-password/verify" style="display:grid;gap:var(--space-3)"><?= Csrf::field() ?><div><label class="b-label" for="recovery-code">Six-digit code sent to <?= View::e($pendingEmail) ?></label><input id="recovery-code" class="b-input" name="otp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></div><button class="b-btn b-btn--primary" type="submit">Verify recovery code</button></form><?php endif; ?>
<p style="margin-top:var(--space-4);font-size:var(--text-xs);text-align:center"><?php if($pendingEmail!==null): ?><a href="/auth/forgot-password?restart=1">Use a different email</a> · <?php endif; ?><a href="/auth/login">Return to sign in</a></p></section></div>
