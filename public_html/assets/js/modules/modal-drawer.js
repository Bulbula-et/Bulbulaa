/**
 * ============================================================================
 * BULBULA.ET V1 — MODAL / BOTTOM-SHEET DRAWER CONTROLLER (C-15 Report / Correction)
 * ============================================================================
 */
(function () {
  'use strict';

  function initDrawers() {
    document.querySelectorAll('[data-open-drawer]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const id = btn.getAttribute('data-open-drawer');
        const backdrop = document.getElementById(id);
        if (!backdrop) return;
        backdrop.classList.add('is-open');
        const firstInput = backdrop.querySelector('select, input, textarea');
        if (firstInput) firstInput.focus();
      });
    });

    document.querySelectorAll('[data-close-drawer]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const backdrop = btn.closest('.b-drawer-backdrop');
        if (backdrop) backdrop.classList.remove('is-open');
      });
    });

    document.querySelectorAll('.b-drawer-backdrop').forEach((backdrop) => {
      backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) {
          backdrop.classList.remove('is-open');
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', initDrawers);
})();
