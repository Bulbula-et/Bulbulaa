(() => {
  'use strict';

  const announce = (message) => {
    let toast = document.querySelector('[data-save-toast]');
    if (!toast) {
      toast = document.createElement('div');
      toast.dataset.saveToast = '';
      toast.className = 'b-save-toast';
      toast.setAttribute('role', 'status');
      toast.setAttribute('aria-live', 'polite');
      document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.classList.add('is-visible');
    window.clearTimeout(toast._hideTimer);
    toast._hideTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 2400);
  };

  const applyState = (button, saved) => {
    button.classList.toggle('is-saved', saved);
    button.setAttribute('aria-pressed', saved ? 'true' : 'false');
    button.setAttribute('aria-label', saved ? 'Remove this place from saved places' : 'Save this place');
    button.title = saved ? 'Remove from saved places' : 'Save place';
    if (saved) {
      button.classList.remove('just-saved');
      void button.offsetWidth;
      button.classList.add('just-saved');
      window.setTimeout(() => button.classList.remove('just-saved'), 450);
    }
  };

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-save-form]');
    if (!form) return;
    event.preventDefault();
    const button = form.querySelector('[data-save-toggle]');
    if (!button || button.classList.contains('is-saving')) return;
    button.classList.add('is-saving');

    const body = new FormData(form);
    body.set('_async', '1');
    try {
      const response = await fetch(form.action, {
        method: 'POST', body, credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });
      const result = await response.json();
      if (!result.ok) {
        if (result.message) announce(result.message);
        if (result.redirect) window.location.assign(result.redirect);
        return;
      }
      const businessId = body.get('business_id');
      const branchId = body.get('branch_id');
      document.querySelectorAll('[data-save-form]').forEach((candidate) => {
        const candidateBusiness = candidate.querySelector('[name="business_id"]')?.value;
        const candidateBranch = candidate.querySelector('[name="branch_id"]')?.value;
        if ((businessId && businessId !== '0' && candidateBusiness === businessId) || (branchId && candidateBranch === branchId)) {
          const candidateButton = candidate.querySelector('[data-save-toggle]');
          if (candidateButton) applyState(candidateButton, Boolean(result.saved));
        }
      });
      announce(result.message || (result.saved ? 'Saved to your places.' : 'Removed from saved places.'));
    } catch (_) {
      announce('Could not update saved places. Please try again.');
    } finally {
      button.classList.remove('is-saving');
    }
  });
})();
