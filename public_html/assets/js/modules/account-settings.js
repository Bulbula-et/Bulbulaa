(() => {
  'use strict';
  let trigger = null;
  const close = (dialog) => { if (dialog?.open) dialog.close(); };

  const sectionLinks = [...document.querySelectorAll('.b-account-nav a[href^="#"]')];
  const sections = sectionLinks.map((link) => document.getElementById(link.hash.slice(1))).filter(Boolean);
  const setCurrentSection = (id) => {
    sectionLinks.forEach((link) => {
      const current = link.hash === `#${id}`;
      link.classList.toggle('is-active', current);
      if (current) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    });
  };
  sectionLinks.forEach((link) => link.addEventListener('click', () => setCurrentSection(link.hash.slice(1))));
  let sectionFrame = 0;
  const syncCurrentSection = () => {
    sectionFrame = 0;
    const marker = Math.min(180, window.innerHeight * 0.3);
    let current = sections[0];
    sections.forEach((section) => { if (section.getBoundingClientRect().top <= marker) current = section; });
    if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) current = sections[sections.length - 1];
    if (current) setCurrentSection(current.id);
  };
  const requestSectionSync = () => { if (!sectionFrame) sectionFrame = requestAnimationFrame(syncCurrentSection); };
  window.addEventListener('scroll', requestSectionSync, { passive: true });
  window.addEventListener('resize', requestSectionSync);
  setCurrentSection(location.hash.slice(1) || 'profile');
  requestSectionSync();

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
