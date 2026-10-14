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
    let requestSequence = 0;
    const hero = dock.closest('.b-wander-hero');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', 'home-search-suggestions');
    dropdown.id = 'home-search-suggestions';

    function escapeHtml(str) {
      const div = document.createElement('div');
      div.textContent = str || '';
      return div.innerHTML;
    }

    function closeDropdown() {
      dropdown.classList.remove('is-open');
      dock.classList.remove('has-open-suggestions');
      if (hero) hero.classList.remove('has-search-open');
      input.setAttribute('aria-expanded', 'false');
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
        const amharic = item.label_am ? `<span class="b-autocomplete__amharic b-lang-am" lang="am">${escapeHtml(item.label_am)}</span>` : '';
        const badgeClass = item.type === 'CATEGORY' ? 'b-badge--blue' : (item.type === 'AREA' ? 'b-badge--orange' : 'b-badge--verified');
        return `
          <a href="${escapeHtml(item.url)}" class="b-autocomplete__item" data-ac-index="${idx}" role="option">
            <span class="b-autocomplete__label">
              <strong>${escapeHtml(item.label_en)}</strong>
              ${amharic}
            </span>
            <span class="b-badge b-autocomplete__type ${badgeClass}">${escapeHtml(item.badge)}</span>
          </a>
        `;
      }).join('');

      const submitOriginalHtml = `
        <a href="/search?q=${encodeURIComponent(query)}" class="b-autocomplete__item b-autocomplete__item--submit" role="option">
          <span>Search directory for &ldquo;${escapeHtml(query)}&rdquo;</span>
          <span aria-hidden="true">Enter &rarr;</span>
        </a>
      `;

      dropdown.innerHTML = rowsHtml + submitOriginalHtml;
      dropdown.classList.add('is-open');
      dock.classList.add('has-open-suggestions');
      if (hero) hero.classList.add('has-search-open');
      input.setAttribute('aria-expanded', 'true');
    }

    input.addEventListener('input', () => {
      const q = input.value.trim();
      clearTimeout(debounceTimer);
      if (q.length < 1) {
        requestSequence++;
        closeDropdown();
        return;
      }

      const sequence = ++requestSequence;
      debounceTimer = setTimeout(async () => {
        try {
          const res = await fetch('/api/v1/autocomplete?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' }
          });
          if (!res.ok || sequence !== requestSequence || input.value.trim() !== q) return;
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
