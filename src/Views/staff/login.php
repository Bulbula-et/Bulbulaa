<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$returnTo = $returnTo ?? '/staff';
$isDebug  = !empty($isDebug);
?>
<div class="b-container" style="padding: var(--space-10) var(--space-5); max-width: 30rem;">
  <section class="b-card" style="padding: var(--space-8); border-top: 4px solid var(--orange-500);">
    <div>
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
        <span class="b-badge b-badge--blue">Protected staff access</span>
        <span class="b-badge b-badge--orange">Web-Only Console</span>
      </div>
      <h1 style="font-size: var(--text-2xl); margin-top: var(--space-3);">Staff Console Sign In</h1>
      <p style="font-size: var(--text-sm); color: var(--neutral-600); margin-top: 0.35rem;">
        Sign in with your Bulbula.et Operator or Administrator credentials to access verification, moderation, and audit queues.
      </p>
    </div>

    <form method="POST" action="/staff/login" style="display: flex; flex-direction: column; gap: var(--space-3); margin-top: var(--space-2);">
      <?= Csrf::field() ?>
      <input type="hidden" name="return_to" value="<?= View::e($returnTo) ?>">

      <div>
        <label class="b-label" for="staff-email">Staff Email</label>
        <input id="staff-email" type="email" name="email" class="b-input" required autocomplete="username" placeholder="name@yourcompany.com">
      </div>

      <div>
        <label class="b-label" for="staff-password">Password</label>
        <input id="staff-password" type="password" name="password" class="b-input" required value="<?= $isDebug ? 'BulbulaStaff2026!' : '' ?>" placeholder="••••••••••••">
      </div>

      <button type="submit" class="b-btn b-btn--primary" style="margin-top: var(--space-2);">
        Sign In to Staff Console &rarr;
      </button>
    </form>

    <?php if ($isDebug): ?>
      <div style="background: var(--orange-50); border: 1px solid var(--orange-200); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); font-size: var(--text-xs); margin-top: var(--space-2);">
        <strong style="color: var(--orange-700);">Seeded Staff Accounts (Password: <code>BulbulaStaff2026!</code>):</strong>
        <ul style="margin: 0.35rem 0 0 1rem; color: var(--neutral-800); line-height: 1.55;">
          <li><code>admin@bulbula.et</code> — Dawit Tadesse (ADMINISTRATOR)</li>
          <li><code>operator@bulbula.et</code> — Meron Alemu (OPERATOR)</li>
          <li><code>qc@bulbula.et</code> — Yonas Bekele (ADMINISTRATOR)</li>
        </ul>
      </div>
    <?php endif; ?>
  </section>
</div>
