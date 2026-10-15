<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\SecurityHeaders;
use Bulbula\Core\View;
?>
<div class="b-container" style="padding:var(--space-12) var(--space-5);max-width:32rem">
  <section class="b-card" style="padding:var(--space-8)">
    <h1>Verify with Google in your browser</h1>
    <p>Use your normal browser to confirm your Google account. Then switch back to the Telegram Mini App to finish signing in. This link expires in five minutes.</p>
    <form id="google-browser-bridge-form" method="POST" action="/auth/google/bridge/verify">
      <?= Csrf::field() ?>
      <input type="hidden" name="flow" value="<?= View::e($flow) ?>">
      <input id="google-browser-credential" type="hidden" name="credential" value="">
    </form>
    <div id="g_id_onload" data-client_id="<?= View::e($googleClientId) ?>" data-callback="bulbulaGoogleBrowserCredential" data-auto_prompt="false" data-ux_mode="popup"></div>
    <div class="g_id_signin" data-type="standard" data-shape="pill" data-theme="outline" data-text="continue_with" data-size="large" data-width="360"></div>
    <p style="font-size:var(--text-xs);color:var(--neutral-600)">Never share this page's URL or a Google verification code.</p>
  </section>
</div>
<script nonce="<?= SecurityHeaders::nonce() ?>">
function bulbulaGoogleBrowserCredential(response) {
  if (!response || !response.credential) return;
  document.getElementById('google-browser-credential').value = response.credential;
  document.getElementById('google-browser-bridge-form').submit();
}
</script>
<script nonce="<?= SecurityHeaders::nonce() ?>" src="https://accounts.google.com/gsi/client" async defer></script>
