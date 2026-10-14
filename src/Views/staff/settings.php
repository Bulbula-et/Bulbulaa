<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$canManage = !empty($canManage);
$env       = $envConfig ?? [];
$sec       = $env['security'] ?? [];
$auth      = $env['auth'] ?? [];
$mail      = $env['mail'] ?? [];
?>
<?php if (!$canManage): ?>
  <div class="b-alert b-alert--error">
    <span>System and authentication configuration is restricted to the Administrator role.</span>
  </div>
<?php else: ?>
  <div class="b-alert b-alert--success" style="margin-bottom: var(--space-4);">
    Production secrets are never written to tracked <code>config/env.json</code>. This page writes an atomic, permission-restricted <code>config/env.local.json</code>; cPanel/server environment variables override it and are preferred.
  </div>
  <form action="/staff/settings/save" method="POST" style="display: flex; flex-direction: column; gap: var(--space-5);">
    <?= Csrf::field() ?>

    <!-- 1. Security & Staff RBAC Mode -->
    <section class="b-card" style="padding: var(--space-5);">
      <h2 style="font-size: var(--text-lg); margin-bottom: var(--space-3);">1. Staff console security and policy timers</h2>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-4);">
        <div>
          <label class="b-label" for="cfg-strict-login">Staff Console Access Mode</label>
          <select id="cfg-strict-login" name="require_staff_login" class="b-select">
            <option value="0" <?= empty($sec['require_staff_login']) ? 'selected' : '' ?>>Pilot Preview Mode (Auto-Sign-In Admin #1)</option>
            <option value="1" <?= !empty($sec['require_staff_login']) ? 'selected' : '' ?>>Strict Production Mode (Require /staff/login Password)</option>
          </select>
        </div>
        <div>
          <label class="b-label" for="cfg-otp-exp">Customer Email OTP Expiry (Seconds)</label>
          <input id="cfg-otp-exp" type="number" name="otp_expiry_seconds" class="b-input" value="<?= (int) ($sec['otp_expiry_seconds'] ?? 600) ?>">
        </div>
        <div>
          <label class="b-label" for="cfg-rev-win">Customer review edit window (days)</label>
          <input id="cfg-rev-win" type="number" name="review_edit_window_days" class="b-input" value="<?= (int) ($sec['review_edit_window_days'] ?? 30) ?>">
        </div>
      </div>
    </section>

    <!-- 2. Google OAuth 2.0 & Telegram Mini App Setup (C-30) -->
    <section class="b-card" style="padding: var(--space-5);">
      <h2 style="font-size: var(--text-lg); margin-bottom: var(--space-3);">2. Google Identity Services and Telegram Mini App</h2>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-4);">
        <div>
          <label class="b-label" for="cfg-gid">Google OAuth Client ID</label>
          <input id="cfg-gid" type="text" name="google_client_id" class="b-input" value="<?= View::e((string) ($auth['google_client_id'] ?? '')) ?>" placeholder="xxxx.apps.googleusercontent.com">
        </div>
        <div>
          <label class="b-label" for="cfg-gsec">Google OAuth Client Secret</label>
          <input id="cfg-gsec" type="password" name="google_client_secret" class="b-input" value="<?= !empty($auth['google_client_secret_is_set']) ? '••••••••' : '' ?>" placeholder="GOCSPX-...">
        </div>
        <div>
          <label class="b-label" for="cfg-tg-user">Telegram Bot Username</label>
          <input id="cfg-tg-user" type="text" name="telegram_bot_username" class="b-input" value="<?= View::e((string) ($auth['telegram_bot_username'] ?? 'BulbulaEtBot')) ?>">
        </div>
        <div>
          <label class="b-label" for="cfg-tg-tok">Telegram Bot Token (HMAC-SHA256)</label>
          <input id="cfg-tg-tok" type="password" name="telegram_bot_token" class="b-input" value="<?= !empty($auth['telegram_bot_token_is_set']) ? '••••••••' : '' ?>" placeholder="123456:ABC-DEF...">
        </div>
      </div>
    </section>

    <!-- 3. Transactional Email OTP SMTP Settings (C-31) -->
    <section class="b-card" style="padding: var(--space-5);">
      <h2 style="font-size: var(--text-lg); margin-bottom: var(--space-3);">3. Transactional email OTP (SMTP / cPanel Mail)</h2>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: var(--space-4);">
        <div>
          <label class="b-label" for="cfg-mdrv">Mail Transport</label>
          <select id="cfg-mdrv" name="mail_driver" class="b-select">
            <option value="smtp" <?= ($mail['driver'] ?? 'smtp') === 'smtp' ? 'selected' : '' ?>>SMTP Socket (Recommended)</option>
            <option value="mail" <?= ($mail['driver'] ?? '') === 'mail' ? 'selected' : '' ?>>PHP mail() (Local cPanel Exim)</option>
          </select>
        </div>
        <div>
          <label class="b-label" for="cfg-mhost">SMTP Host</label>
          <input id="cfg-mhost" type="text" name="mail_host" class="b-input" value="<?= View::e((string) ($mail['host'] ?? 'mail.bulbula.et')) ?>">
        </div>
        <div>
          <label class="b-label" for="cfg-mport">SMTP Port</label>
          <input id="cfg-mport" type="number" name="mail_port" class="b-input" value="<?= (int) ($mail['port'] ?? 587) ?>">
        </div>
        <div>
          <label class="b-label" for="cfg-menc">Encryption</label>
          <select id="cfg-menc" name="mail_encryption" class="b-select">
            <option value="tls" <?= ($mail['encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (Port 587)</option>
            <option value="ssl" <?= ($mail['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL/TLS (Port 465)</option>
            <option value="none" <?= ($mail['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
          </select>
        </div>
        <div>
          <label class="b-label" for="cfg-muser">SMTP Username</label>
          <input id="cfg-muser" type="text" name="mail_username" class="b-input" value="<?= View::e((string) ($mail['username'] ?? 'no-reply@bulbula.et')) ?>">
        </div>
        <div>
          <label class="b-label" for="cfg-mpass">SMTP Password</label>
          <input id="cfg-mpass" type="password" name="mail_password" class="b-input" value="<?= !empty($mail['password_is_set']) ? '••••••••' : '' ?>" placeholder="••••••••">
        </div>
        <div>
          <label class="b-label" for="cfg-mfrom">From Email</label>
          <input id="cfg-mfrom" type="email" name="mail_from_email" class="b-input" value="<?= View::e((string) ($mail['from_email'] ?? 'no-reply@bulbula.et')) ?>">
        </div>
        <div>
          <label class="b-label" for="cfg-mname">From Name</label>
          <input id="cfg-mname" type="text" name="mail_from_name" class="b-input" value="<?= View::e((string) ($mail['from_name'] ?? 'Bulbula.et Verification')) ?>">
        </div>
      </div>

      <div style="margin-top: var(--space-4); display: flex; justify-content: flex-end;">
        <button type="submit" class="b-btn b-btn--primary">Save &amp; Hot-Reload Configuration</button>
      </div>
    </section>
  </form>

  <section class="b-card" style="padding: var(--space-5); margin-top: var(--space-5);">
    <h2 style="font-size: var(--text-lg); margin-bottom: var(--space-2);">4. Live SMTP Delivery Test</h2>
    <p style="font-size: var(--text-sm); color: var(--neutral-600); margin-bottom: var(--space-3);">Save your SMTP settings first, then send a real test through the configured transport.</p>
    <form action="/staff/settings/test-email" method="POST" style="display: flex; gap: var(--space-3); align-items: end; flex-wrap: wrap;">
      <?= Csrf::field() ?>
      <div style="flex: 1 1 240px;">
        <label class="b-label" for="cfg-test-email">Test Recipient</label>
        <input id="cfg-test-email" type="email" name="test_email" class="b-input" placeholder="admin@bulbula.et" required>
      </div>
      <button type="submit" class="b-btn b-btn--outline">Send SMTP Test</button>
    </form>
  </section>
<?php endif; ?>
