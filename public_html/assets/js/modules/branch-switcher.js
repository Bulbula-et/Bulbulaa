/**
 * ============================================================================
 * BULBULA.ET V1 — MULTI-BRANCH CONTEXT SWITCHER (C-08), LIVE VENDOR FILTER
 * & INTERACTIVE BRANCH PHOTO GALLERY
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
      filterPills.forEach((pill, index) => {
        pill.tabIndex = pill.getAttribute('aria-selected') === 'true' ? 0 : -1;
        pill.addEventListener('click', (e) => {
          e.preventDefault();
          const targetCat = pill.getAttribute('data-vendor-filter') || 'all';

          filterPills.forEach((p) => {
            const active = p === pill;
            p.classList.toggle('is-active', active);
            p.setAttribute('aria-selected', active ? 'true' : 'false');
            p.tabIndex = active ? 0 : -1;
          });

          vendorRows.forEach((row) => {
            const rowCat = row.getAttribute('data-vendor-category') || '';
            const matches = targetCat === 'all' || rowCat === targetCat;
            row.style.display = matches ? '' : 'none';
          });
        });
        pill.addEventListener('keydown', (e) => {
          let next = index;
          if (e.key === 'ArrowRight') next = (index + 1) % filterPills.length;
          else if (e.key === 'ArrowLeft') next = (index - 1 + filterPills.length) % filterPills.length;
          else if (e.key === 'Home') next = 0;
          else if (e.key === 'End') next = filterPills.length - 1;
          else return;
          e.preventDefault();
          filterPills[next].focus();
          filterPills[next].click();
        });
      });
    }

    // Interactive 3-Photo Carousel Dots on Branch Detail Page
    const gallery = document.querySelector('[data-profile-gallery]');
    if (gallery) {
      const mainImg = gallery.querySelector('[data-gallery-main-img]');
      const caption = gallery.querySelector('[data-gallery-caption]');
      const dots = Array.from(gallery.querySelectorAll('[data-gallery-dot]'));
      let currentIdx = 0;

      const setSlide = (idx) => {
        const dot = dots[idx];
        if (!dot || !mainImg) return;
        currentIdx = idx;
        mainImg.src = dot.getAttribute('data-gallery-src') || mainImg.src;
        if (caption) {
          caption.textContent = dot.getAttribute('data-gallery-label') || '';
        }
        dots.forEach((d, i) => {
          d.style.background = i === idx ? '#FFFFFF' : 'rgba(255,255,255,0.45)';
        });
      };

      dots.forEach((dot, idx) => {
        dot.addEventListener('click', () => setSlide(idx));
      });

      if (mainImg && dots.length > 1) {
        mainImg.addEventListener('click', () => {
          setSlide((currentIdx + 1) % dots.length);
        });
      }
    }
  });
})();
