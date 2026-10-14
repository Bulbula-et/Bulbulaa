/**
 * ============================================================================
 * BULBULA.ET V1 — LIVE SEARCH AUTOCOMPLETE MODULE (C-03)
 * Guides visitors toward controlled Categories, Subcategories, Areas, and Branches
 * while always permitting submission of the original free-text query.
 * ============================================================================
 */
(function () {
  'use strict';

  function initAutocomplete() {
    const dock = document.querySelector('[data-autocomplete-dock]');
    if (!dock) return;

    const input = dock.querySelector('[data-autocomplete-input]');
    const dropdown = dock.querySelector('[data-autocomplete-dropdown]');
    if (!input || !dropdown) return;

    let debounceTimer = null;
    let currentItems = [];
    let activeIndex = -1;

    function escapeHtml(str) {
      const div = document.createElement('div');
      div.textContent = str || '';
      return div.innerHTML;
    }

    function closeDropdown() {
      dropdown.classList.remove('is-open');
      dropdown.innerHTML = '';
      currentItems = [];
      activeIndex = -1;
    }

    function renderSuggestions(query, suggestions) {
      if (!Array.isArray(suggestions) || suggestions.length === 0) {
        closeDropdown();
        return;
      }

      currentItems = suggestions;
      activeIndex = -1;

      const rowsHtml = suggestions.map((item, idx) => {
        const amharic = item.label_am ? `<span class="b-lang-am" style="color: var(--neutral-600); margin-left: 0.4rem;">(${escapeHtml(item.label_am)})</span>` : '';
        const badgeClass = item.type === 'CATEGORY' ? 'b-badge--blue' : (item.type === 'AREA' ? 'b-badge--orange' : 'b-badge--verified');
        return `
          <a href="${escapeHtml(item.url)}" class="b-autocomplete__item" data-ac-index="${idx}">
            <div>
              <strong>${escapeHtml(item.label_en)}</strong>
              ${amharic}
            </div>
            <span class="b-badge ${badgeClass}">${escapeHtml(item.badge)}</span>
          </a>
        `;
      }).join('');

      const submitOriginalHtml = `
        <a href="/search?q=${encodeURIComponent(query)}" class="b-autocomplete__item" style="background: var(--neutral-50); font-weight: 600; color: var(--orange-600);">
          <span>Search directory for &ldquo;${escapeHtml(query)}&rdquo;</span>
          <span>Enter &rarr;</span>
        </a>
      `;

      dropdown.innerHTML = rowsHtml + submitOriginalHtml;
      dropdown.classList.add('is-open');
    }

    input.addEventListener('input', () => {
      const q = input.value.trim();
      clearTimeout(debounceTimer);
      if (q.length < 1) {
        closeDropdown();
        return;
      }

      debounceTimer = setTimeout(async () => {
        try {
          const res = await fetch('/api/v1/autocomplete?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' }
          });
          if (!res.ok) return;
          const data = await res.json();
          renderSuggestions(q, data.suggestions || []);
        } catch (_) {}
      }, 140);
    });

    input.addEventListener('keydown', (e) => {
      if (!dropdown.classList.contains('is-open')) return;
      const links = dropdown.querySelectorAll('.b-autocomplete__item');
      if (links.length === 0) return;

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex = (activeIndex + 1) % links.length;
        links.forEach((el, i) => el.classList.toggle('is-highlighted', i === activeIndex));
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex = (activeIndex - 1 + links.length) % links.length;
        links.forEach((el, i) => el.classList.toggle('is-highlighted', i === activeIndex));
      } else if (e.key === 'Enter' && activeIndex >= 0 && links[activeIndex]) {
        e.preventDefault();
        window.location.href = links[activeIndex].getAttribute('href');
      } else if (e.key === 'Escape') {
        closeDropdown();
      }
    });

    document.addEventListener('click', (e) => {
      if (!dock.contains(e.target)) {
        closeDropdown();
      }
    });
  }

  document.addEventListener('DOMContentLoaded', initAutocomplete);
})();
