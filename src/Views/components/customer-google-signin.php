<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\SecurityHeaders;
use Bulbula\Core\View;
$returnTo=$returnTo??'/account';
?>
      <div id="google-web-signin">
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
      </div>
      <form id="google-telegram-signin" method="POST" action="/auth/google/bridge/start" hidden>
        <?= Csrf::field() ?>
        <input type="hidden" name="return_to" value="<?= View::e($returnTo) ?>">
        <button type="submit" class="b-btn b-btn--outline">Continue with Google in a browser</button>
        <p style="font-size:var(--text-xs);color:var(--neutral-600)">Google does not support sign-in inside Telegram's embedded browser. Return to Telegram after verifying in your browser.</p>
      </form>
      <script nonce="<?= SecurityHeaders::nonce() ?>">
        function bulbulaGoogleCredential(response) {
          if (!response || !response.credential) return;
          document.getElementById('google-credential').value = response.credential;
          document.getElementById('google-auth-form').submit();
        }
        (function () {
          const tg = window.Telegram && window.Telegram.WebApp;
          const platform = tg && typeof tg.platform === 'string' ? tg.platform.toLowerCase() : '';
          const inMiniApp = tg && ((platform !== '' && platform !== 'unknown') || (typeof tg.initData === 'string' && tg.initData.length > 0));
          if (inMiniApp) {
            document.getElementById('google-web-signin').hidden = true;
            document.getElementById('google-telegram-signin').hidden = false;
          } else {
            const script = document.createElement('script');
            script.src = 'https://accounts.google.com/gsi/client';
            script.async = true;
            document.head.appendChild(script);
          }
        })();
      </script>
