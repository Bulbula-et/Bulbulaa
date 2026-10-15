/* Telegram account linking: raw initData is sent only to the same-origin verifier, never stored. */
(() => {
  const webApp = window.Telegram && window.Telegram.WebApp;
  const raw = webApp && webApp.initData;
  const body = document.body;
  if (!raw || !body || !body.dataset.telegramAuthCsrf) return;
  const csrf = body.dataset.telegramAuthCsrf;
  const panel = document.querySelector('[data-telegram-link-action]');
  let busy = false;

  async function submit(operation = 'status', confirm = '') {
    if (busy) return null;
    busy = true;
    try {
      const response = await fetch('/auth/telegram/session', {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
        body: new URLSearchParams({ _csrf: csrf, init_data: raw, operation, confirm })
      });
      return await response.json();
    } catch (_) { return null; }
    finally { busy = false; }
  }

  function message(text, actionLabel, action) {
    let box = document.getElementById('telegram-auth-notice');
    if (!box) {
      box = document.createElement('aside'); box.id = 'telegram-auth-notice';
      box.className = 'b-alert b-alert--info'; box.setAttribute('role', 'status');
      box.style.cssText = 'margin:1rem auto;max-width:42rem;padding:1rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap';
      const main = document.querySelector('main.b-main');
      if (main) main.prepend(box); else body.prepend(box);
    }
    box.replaceChildren();
    const label = document.createElement('span'); label.textContent = text; box.append(label);
    if (actionLabel && action) {
      const button = document.createElement('button'); button.type = 'button';
      button.className = 'b-btn b-btn--primary b-btn--sm'; button.textContent = actionLabel;
      button.addEventListener('click', action); box.append(button);
    }
  }

  async function init() {
    const result = await submit();
    if (!result) return;
    if (result.state === 'signed_in') { window.location.reload(); return; }
    if (result.state === 'switch_confirmation') {
      message(`This Telegram account is linked to ${result.target_name} (${result.target_email}), not the Bulbula account currently signed in here. Switching will not merge saved places or reviews.`, 'Switch to linked account', async () => {
        if (!window.confirm('Switch to the linked Bulbula account? Your other Google account and its data will remain separate.')) return;
        const switched = await submit('switch', 'yes');
        if (switched && switched.state === 'signed_in') window.location.assign('/account');
        else message('Could not switch. Reopen the Mini App and try again.');
      });
    }
    if (result.state === 'unlinked' && body.dataset.telegramAccount === 'yes' && panel && result.can_link && !result.already_linked) {
      const label = document.createElement('label');
      const check = document.createElement('input'); check.type = 'checkbox';
      label.append(check, document.createTextNode(' Link this Telegram account to the Google-backed Bulbula account shown above. Other Google accounts and their data will not be merged.'));
      const button = document.createElement('button'); button.type = 'button';
      button.className = 'b-btn b-btn--primary b-btn--sm'; button.textContent = 'Link Telegram to this account'; button.disabled = true;
      check.addEventListener('change', () => { button.disabled = !check.checked; });
      button.addEventListener('click', async () => {
        button.disabled = true;
        const linked = await submit('link', 'yes');
        if (linked && linked.state === 'linked') window.location.reload();
        else { button.disabled = !check.checked; message('Linking failed. This Telegram identity or Bulbula account may already be linked elsewhere.'); }
      });
      panel.hidden = false; panel.append(label, button);
    }
    if (result.state === 'unlinked' && body.dataset.telegramAccount === 'no') {
      message('To use the same Bulbula account on all Telegram devices, first sign in to your primary Google-backed account here, then link Telegram from Account settings.');
    }
    if (result.state === 'invalid_launch') message('Telegram verification expired. Close and reopen the Mini App to refresh it.');
  }
  init();
})();
