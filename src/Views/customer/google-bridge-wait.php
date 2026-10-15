<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\SecurityHeaders;
use Bulbula\Core\View;
?>
<div class="b-container" style="padding:var(--space-12) var(--space-5);max-width:32rem">
  <section class="b-card" style="padding:var(--space-8)">
    <h1>Continue with Google</h1>
    <p>Open the secure sign-in page in your browser. When Google verifies your account, switch back to this Telegram Mini App; your sign-in will finish here automatically. This link expires in five minutes.</p>
    <a id="google-bridge-open" class="b-btn b-btn--primary" href="<?= View::e($browserUrl) ?>" target="_blank" rel="noopener noreferrer">Open Google sign-in in browser</a>
    <p id="google-bridge-status" role="status" aria-live="polite" style="margin-top:var(--space-4)">Waiting for Google verification. Do not share the browser link.</p>
    <a href="/auth/login">Cancel and return to sign in</a>
  </section>
</div>
<script nonce="<?= SecurityHeaders::nonce() ?>">
(function () {
  const open = document.getElementById('google-bridge-open');
  const status = document.getElementById('google-bridge-status');
  open.addEventListener('click', function (event) {
    const tg = window.Telegram && window.Telegram.WebApp;
    if (tg && typeof tg.openLink === 'function' && ((tg.platform && tg.platform !== 'unknown') || (typeof tg.initData === 'string' && tg.initData.length > 0))) {
      try { tg.openLink(open.href, { try_instant_view: false }); event.preventDefault(); }
      catch (_) { /* Allow the normal external-link fallback. */ }
    }
  });
  const csrf = <?= json_encode(Csrf::token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  let busy = false;
  const expiresAt = Date.now() + 300000;
  async function check() {
    if (busy) return;
    if (Date.now() >= expiresAt) { status.textContent = 'This sign-in link expired. Return to sign in and try again.'; clearInterval(timer); return; }
    busy = true;
    try {
      const response = await fetch('/auth/google/bridge/complete', {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
        body: new URLSearchParams({ _csrf: csrf })
      });
      const result = await response.json();
      if (result.status === 'complete' && result.redirect) {
        clearInterval(timer);
        status.textContent = 'Google verified. Opening your account...';
        window.location.assign(result.redirect);
      } else if (result.status === 'expired' || result.status === 'invalid') {
        clearInterval(timer);
        status.textContent = 'This sign-in session ended. Return to sign in and try again.';
      }
    } catch (_) { status.textContent = 'Waiting for a connection. Keep this page open and return from your browser.'; }
    finally { busy = false; }
  }
  const timer = setInterval(check, 2500);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });
  check();
})();
</script>
