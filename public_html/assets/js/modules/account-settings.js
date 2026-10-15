(() => {
  'use strict';
  let trigger = null;
  const close = (dialog) => { if (dialog?.open) dialog.close(); };
  document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-dialog-open]');
    if (opener) {
      const dialog = document.getElementById(opener.dataset.dialogOpen);
      if (dialog?.showModal) { trigger = opener; dialog.showModal(); const safe = dialog.querySelector('[autofocus],[data-dialog-close]'); safe?.focus(); }
      return;
    }
    const closer = event.target.closest('[data-dialog-close]');
    if (closer) close(closer.closest('dialog'));
  });
  document.querySelectorAll('.b-account-dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => { if (event.target === dialog && !dialog.classList.contains('b-account-dialog--danger')) close(dialog); });
    dialog.addEventListener('close', () => { trigger?.focus(); trigger = null; });
  });
  document.querySelectorAll('[data-dialog-auto-open]').forEach((dialog) => { if (dialog.showModal && !dialog.open) { dialog.showModal(); dialog.querySelector('[data-dialog-close]')?.focus(); } });
  const confirmation = document.querySelector('[data-delete-confirm]');
  const destructive = document.querySelector('[data-delete-submit]');
  if (confirmation && destructive) confirmation.addEventListener('input', () => { destructive.disabled = confirmation.value !== 'DELETE'; });
})();
