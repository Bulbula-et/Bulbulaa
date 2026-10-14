<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$canManage = !empty($canManage);
$env = $envConfig ?? [];
$sec = $env['security'] ?? [];
$auth = $env['auth'] ?? [];
$mail = $env['mail'] ?? [];
$strictAccess = !empty($sec['require_staff_login']);
$identityConfigured = !empty($auth['google_client_id']) || !empty($auth['telegram_bot_token_is_set']);
$mailConfigured = !empty($mail['host']) && !empty($mail['from_email']);
?>
<?php if (!$canManage): ?>
  <section class="b-settings-denied"><strong>Administrator authorization required</strong><p>System, authentication, and delivery configuration is restricted to authenticated Administrators.</p></section>
<?php else: ?>
  <section class="b-settings-status">
    <div class="b-settings-status__main"><h2>Protected runtime configuration</h2><p>Changes are validated, written atomically to a permission-restricted server file, and activated without restarting PHP or the web server. Server environment variables remain authoritative.</p><span class="b-settings-status__note">Secrets excluded from tracked configuration</span></div>
    <article class="b-settings-stat"><span class="b-settings-stat__label">Staff access</span><strong class="b-settings-stat__value"><?= $strictAccess ? 'Strict' : 'Preview' ?></strong><span class="b-settings-stat__meta"><?= $strictAccess ? 'Password and 2FA required' : 'Development access mode' ?></span></article>
    <article class="b-settings-stat"><span class="b-settings-stat__label">Identity providers</span><strong class="b-settings-stat__value"><?= $identityConfigured ? 'Configured' : 'Not configured' ?></strong><span class="b-settings-stat__meta">Google and Telegram customer access</span></article>
    <article class="b-settings-stat"><span class="b-settings-stat__label">Email delivery</span><strong class="b-settings-stat__value"><?= $mailConfigured ? 'Configured' : 'Not configured' ?></strong><span class="b-settings-stat__meta">Customer verification transport</span></article>
  </section>

  <div class="b-settings-form">
    <section class="b-card b-settings-card">
      <header class="b-settings-card__head"><div class="b-settings-card__title"><span class="b-settings-card__index">1</span><div><h2>Security and policy timers</h2><p>Control Staff authentication and customer-account time windows.</p></div></div><span class="b-settings-card__state">Security sensitive</span></header>
      <form action="/staff/settings/save" method="POST" autocomplete="off">
      <?= Csrf::field() ?><input type="hidden" name="settings_section" value="security">
      <div class="b-settings-card__body">
        <div><label class="b-label" for="cfg-strict-login">Staff access mode</label><select id="cfg-strict-login" name="require_staff_login" class="b-select"><option value="0" <?= !$strictAccess ? 'selected' : '' ?>>Development preview</option><option value="1" <?= $strictAccess ? 'selected' : '' ?>>Strict production access</option></select><span class="b-settings-help">Production requires individual credentials and authenticator verification.</span></div>
        <div><label class="b-label" for="cfg-otp-exp">Email code expiry</label><input id="cfg-otp-exp" type="number" name="otp_expiry_seconds" class="b-input" value="<?= (int) ($sec['otp_expiry_seconds'] ?? 600) ?>" min="60" max="3600" step="30" required><span class="b-settings-help">Seconds before a customer verification code expires.</span></div>
        <div><label class="b-label" for="cfg-rev-win">Review edit window</label><input id="cfg-rev-win" type="number" name="review_edit_window_days" class="b-input" value="<?= (int) ($sec['review_edit_window_days'] ?? 30) ?>" min="1" max="90" required><span class="b-settings-help">Days during which a customer may edit their review.</span></div>
      </div>
      <div class="b-settings-section-save"><span>Only this section will be validated and changed.</span><button type="submit" class="b-btn b-btn--primary b-btn--sm">Save security settings</button></div>
      </form>
    </section>

    <section class="b-card b-settings-card">
      <header class="b-settings-card__head"><div class="b-settings-card__title"><span class="b-settings-card__index">2</span><div><h2>Customer identity providers</h2><p>Configure Google sign-in and Telegram Mini App authentication.</p></div></div><span class="b-settings-card__state"><?= $identityConfigured ? 'Partially configured' : 'Setup required' ?></span></header>
      <form action="/staff/settings/save" method="POST" autocomplete="off">
      <?= Csrf::field() ?><input type="hidden" name="settings_section" value="auth">
      <div class="b-settings-card__body b-settings-card__body--auth">
        <div><label class="b-label" for="cfg-gid">Google client ID</label><input id="cfg-gid" type="text" name="google_client_id" class="b-input" value="<?= View::e((string) ($auth['google_client_id'] ?? '')) ?>" placeholder="Client ID from Google Cloud" autocomplete="off"></div>
        <div><label class="b-label" for="cfg-gsec">Google client secret</label><input id="cfg-gsec" type="password" name="google_client_secret" class="b-input" value="<?= !empty($auth['google_client_secret_is_set']) ? '••••••••' : '' ?>" placeholder="Enter client secret" autocomplete="new-password"><span class="b-settings-help">Leave the masked value unchanged to retain the stored secret.</span></div>
        <div><label class="b-label" for="cfg-tg-user">Telegram bot username</label><input id="cfg-tg-user" type="text" name="telegram_bot_username" class="b-input" value="<?= View::e((string) ($auth['telegram_bot_username'] ?? '')) ?>" placeholder="YourBotUsername" autocomplete="off"></div>
        <div><label class="b-label" for="cfg-tg-tok">Telegram bot token</label><input id="cfg-tg-tok" type="password" name="telegram_bot_token" class="b-input" value="<?= !empty($auth['telegram_bot_token_is_set']) ? '••••••••' : '' ?>" placeholder="Enter bot token" autocomplete="new-password"><span class="b-settings-help">Used only for server-side signature verification.</span></div>
      </div>
      <div class="b-settings-section-save"><span>SMTP and security settings will not be validated or changed.</span><button type="submit" class="b-btn b-btn--primary b-btn--sm">Save identity providers</button></div>
      </form>
    </section>

    <section class="b-card b-settings-card">
      <header class="b-settings-card__head"><div class="b-settings-card__title"><span class="b-settings-card__index">3</span><div><h2>Transactional email delivery</h2><p>Configure the transport used for customer verification codes.</p></div></div><span class="b-settings-card__state"><?= $mailConfigured ? 'Transport configured' : 'Setup required' ?></span></header>
      <form action="/staff/settings/save" method="POST" autocomplete="off">
      <?= Csrf::field() ?><input type="hidden" name="settings_section" value="mail">
      <div class="b-settings-card__body b-settings-card__body--mail">
        <div><label class="b-label" for="cfg-mdrv">Mail transport</label><select id="cfg-mdrv" name="mail_driver" class="b-select"><option value="smtp" <?= ($mail['driver'] ?? 'smtp') === 'smtp' ? 'selected' : '' ?>>Authenticated SMTP</option><option value="mail" <?= ($mail['driver'] ?? '') === 'mail' ? 'selected' : '' ?>>Local PHP mail transport</option></select></div>
        <div><label class="b-label" for="cfg-mhost">SMTP host</label><input id="cfg-mhost" type="text" name="mail_host" class="b-input" value="<?= View::e((string) ($mail['host'] ?? '')) ?>" placeholder="mail.yourdomain.com" autocomplete="off"></div>
        <div><label class="b-label" for="cfg-mport">SMTP port</label><input id="cfg-mport" type="number" name="mail_port" class="b-input" value="<?= (int) ($mail['port'] ?? 587) ?>" min="1" max="65535" required></div>
        <div><label class="b-label" for="cfg-menc">Encryption</label><select id="cfg-menc" name="mail_encryption" class="b-select"><option value="tls" <?= ($mail['encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS</option><option value="ssl" <?= ($mail['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>Implicit TLS</option><option value="none" <?= ($mail['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>No encryption</option></select></div>
        <div><label class="b-label" for="cfg-muser">SMTP username</label><input id="cfg-muser" type="text" name="mail_username" class="b-input" value="<?= View::e((string) ($mail['username'] ?? '')) ?>" placeholder="mailbox@yourdomain.com" autocomplete="off"></div>
        <div><label class="b-label" for="cfg-mpass">SMTP password</label><input id="cfg-mpass" type="password" name="mail_password" class="b-input" value="<?= !empty($mail['password_is_set']) ? '••••••••' : '' ?>" placeholder="Enter mailbox password" autocomplete="new-password"></div>
        <div><label class="b-label" for="cfg-mfrom">Sender email</label><input id="cfg-mfrom" type="email" name="mail_from_email" class="b-input" value="<?= View::e((string) ($mail['from_email'] ?? '')) ?>" placeholder="no-reply@yourdomain.com" autocomplete="off"></div>
        <div><label class="b-label" for="cfg-mname">Sender name</label><input id="cfg-mname" type="text" name="mail_from_name" class="b-input" value="<?= View::e((string) ($mail['from_name'] ?? '')) ?>" placeholder="Bulbula.et Verification" autocomplete="off"></div>
      </div>
      <div class="b-settings-section-save"><span>Only email-delivery settings will be validated and changed.</span><button type="submit" class="b-btn b-btn--primary b-btn--sm">Save email delivery</button></div>
      </form>
    </section>
  </div>

  <section class="b-card b-settings-card" style="margin-top:16px">
    <header class="b-settings-card__head"><div class="b-settings-card__title"><span class="b-settings-card__index">4</span><div><h2>Email delivery test</h2><p>Send a real message through the currently active transport.</p></div></div></header>
    <form action="/staff/settings/test-email" method="POST" class="b-settings-test"><?= Csrf::field() ?><div><label class="b-label" for="cfg-test-email">Test recipient</label><input id="cfg-test-email" type="email" name="test_email" class="b-input" placeholder="recipient@example.com" autocomplete="off" required></div><button type="submit" class="b-btn b-btn--outline">Send delivery test</button></form>
  </section>
<?php endif; ?>
