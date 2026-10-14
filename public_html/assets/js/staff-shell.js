(() => {
  'use strict';
  const body = document.body;
  const open = () => { body.classList.add('is-sidebar-open'); document.querySelector('.b-staff-sidebar a')?.focus(); };
  const close = () => body.classList.remove('is-sidebar-open');
  document.querySelector('[data-sidebar-open]')?.addEventListener('click', open);
  document.querySelectorAll('[data-sidebar-close]').forEach((item) => item.addEventListener('click', close));
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
})();
