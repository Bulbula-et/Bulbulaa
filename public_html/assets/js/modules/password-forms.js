/* Progressive enhancement: server remains the final validator; no password leaves this browser for scoring. */
(() => {
  const trivial = new Set(['12345678','123456789','1234567890','87654321','00000000','11111111','password','password1','password12','password123','qwerty123','qwertyui','admin123','letmein1','abcdefgh','asdfghjk']);
  const labels = ['Very weak', 'Weak', 'Fair', 'Good', 'Strong'];
  const length = value => Array.from(value).length;

  for (const form of document.querySelectorAll('[data-customer-password-form]')) {
    const password = form.querySelector('[data-password]');
    const confirm = form.querySelector('[data-confirm]');
    if (!password || !confirm) continue;
    const name = form.querySelector('[name="display_name"]');
    const email = form.querySelector('[name="email"]');
    const meterBox = form.querySelector('[data-strength]');
    const meter = meterBox && meterBox.querySelector('meter');
    const strengthLabel = meterBox && meterBox.querySelector('[data-strength-label]');
    const status = form.querySelector('[data-form-status]');
    const touched = new Set();
    let timer;

    function errorFor(input) {
      if (input === name) {
        const n = length(name.value.trim());
        return n < 2 || n > 100 ? 'Enter your name (2–100 characters).' : '';
      }
      if (input === email) {
        return !email.value.trim() || !email.validity.valid ? 'Enter a valid email address.' : '';
      }
      if (input === password) {
        const n = length(password.value);
        if (n < 8) return `Add ${8 - n} more character${8 - n === 1 ? '' : 's'} (minimum 8).`;
        if (n > 128) return 'Use no more than 128 characters.';
        if (password.value.includes('\0')) return 'Use text characters for your password.';
        if (trivial.has(password.value.toLowerCase())) return 'This password is very common. Choose a different one.';
        return '';
      }
      if (input === confirm) return confirm.value !== password.value ? 'Passwords do not match yet.' : '';
      return '';
    }

    function render(input, force = false) {
      if (!input || (!force && !touched.has(input))) return true;
      const error = errorFor(input);
      const hint = input.getAttribute('aria-describedby')?.split(' ').map(id => document.getElementById(id)).find(node => node && node.id.endsWith('-error'));
      if (hint) hint.textContent = error;
      if (error) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
      return !error;
    }

    function updateStrength() {
      if (!meterBox || !meter || !strengthLabel) return;
      if (!password.value) { meterBox.hidden = true; strengthLabel.textContent = ''; return; }
      meterBox.hidden = false;
      if (typeof window.BulbulaPasswordStrength !== 'function') {
        strengthLabel.textContent = 'Strength estimate unavailable. Password checks still work.';
        return;
      }
      try {
        const inputs = [name?.value, email?.value].filter(Boolean);
        const result = window.BulbulaPasswordStrength(password.value, inputs);
        const score = Math.max(0, Math.min(4, Number(result.score) || 0));
        meter.value = score;
        meterBox.dataset.score = String(score);
        const tip = result.feedback?.warning || result.feedback?.suggestions?.[0] || '';
        strengthLabel.textContent = `Strength: ${labels[score]}.${tip ? ' ' + tip : ''} This is guidance, not a requirement.`;
      } catch (_) { strengthLabel.textContent = 'Strength estimate unavailable. Password checks still work.'; }
    }

    const inputs = [name, email, password, confirm].filter(Boolean);
    for (const input of inputs) {
      input.addEventListener('input', () => {
        touched.add(input);
        render(input);
        if (input === password) {
          if (touched.has(confirm)) render(confirm);
          clearTimeout(timer); timer = setTimeout(updateStrength, 120);
        }
        if (status) status.textContent = '';
      });
      input.addEventListener('blur', () => { touched.add(input); render(input); });
    }
    form.querySelector('[data-password-reveal]')?.addEventListener('click', event => {
      const visible = password.type !== 'text';
      password.type = visible ? 'text' : 'password';
      confirm.type = visible ? 'text' : 'password';
      event.currentTarget.textContent = visible ? 'Hide passwords' : 'Show passwords';
      event.currentTarget.setAttribute('aria-pressed', String(visible));
    });
    form.addEventListener('submit', event => {
      let firstInvalid = null;
      for (const input of inputs) {
        touched.add(input);
        if (!render(input, true) && !firstInvalid) firstInvalid = input;
      }
      if (firstInvalid) {
        event.preventDefault();
        if (status) status.textContent = 'Please fix the highlighted fields before continuing.';
        firstInvalid.focus();
      } else {
        if (status) status.textContent = form.hasAttribute('data-registration-form') ? 'Sending your verification code…' : 'Saving your password…';
        const submit = form.querySelector('[type="submit"]');
        if (submit) { submit.disabled = true; submit.setAttribute('aria-busy', 'true'); }
      }
    });
  }
})();
