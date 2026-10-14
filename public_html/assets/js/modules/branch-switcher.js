/**
 * ============================================================================
 * BULBULA.ET V1 — MULTI-BRANCH CONTEXT SWITCHER (C-08) & LIVE VENDOR FILTER
 * ============================================================================
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const select = document.querySelector('[data-branch-switcher]');
    if (select) {
      select.addEventListener('change', () => {
        if (select.value) {
          window.location.href = select.value;
        }
      });
    }

    // Live instant category filter pills on Homepage "Top Branches Near You"
    const filterPills = document.querySelectorAll('[data-vendor-filter]');
    const vendorRows = document.querySelectorAll('[data-vendor-list] [data-vendor-category]');
    if (filterPills.length > 0 && vendorRows.length > 0) {
      filterPills.forEach((pill) => {
        pill.addEventListener('click', (e) => {
          e.preventDefault();
          const targetCat = pill.getAttribute('data-vendor-filter') || 'all';

          filterPills.forEach((p) => {
            const active = p === pill;
            p.classList.toggle('is-active', active);
            p.setAttribute('aria-selected', active ? 'true' : 'false');
          });

          vendorRows.forEach((row) => {
            const rowCat = row.getAttribute('data-vendor-category') || '';
            const matches = targetCat === 'all' || rowCat === targetCat;
            row.style.display = matches ? '' : 'none';
          });
        });
      });
    }
  });
})();
