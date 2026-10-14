/**
 * ============================================================================
 * BULBULA.ET V1 — UNIFIED WEB & TELEGRAM MINI APP SURFACE ADAPTER (C-17)
 * 1 Single Frontend for Mobile Web, Telegram Mini App (Mobile & Desktop),
 * Tablet, and Desktop Web.
 * ============================================================================
 */
(function () {
  'use strict';

  const tg = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;

  const SurfaceAdapter = {
    isTelegramNative: Boolean(tg && tg.initData),

    init() {
      // Automatically adapt when opened inside Telegram Mini App (Mobile or Desktop)
      if (tg) {
        try {
          tg.ready();
          tg.expand();
          if (typeof tg.setHeaderColor === 'function') {
            tg.setHeaderColor('#FFFAF5');
          }
          if (typeof tg.setBackgroundColor === 'function') {
            tg.setBackgroundColor('#FFFAF5');
          }
        } catch (_) {}
      }

      // Subtle tactile haptic feedback on bottom nav dock & primary actions in Telegram
      document.querySelectorAll('.b-bottom-nav__item, .b-btn, .b-chip').forEach((el) => {
        el.addEventListener('click', () => {
          if (tg && tg.HapticFeedback && typeof tg.HapticFeedback.impactOccurred === 'function') {
            try {
              tg.HapticFeedback.impactOccurred('light');
            } catch (_) {}
          }
        });
      });

      // Bind C-17 Canonical URL Share buttons across the page
      document.querySelectorAll('[data-bulbula-share], [data-share-btn]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const title = btn.getAttribute('data-share-title') || document.title;
          const relUrl = btn.getAttribute('data-share-url') || window.location.pathname;
          const canonicalUrl = new URL(relUrl, window.location.origin).toString();
          this.shareCanonical(title, canonicalUrl, btn);
        });
      });

      // Bind C-11 / C-38 server-side contact/share action telemetry pings
      document.querySelectorAll('[data-branch-action]').forEach((el) => {
        el.addEventListener('click', () => {
          const endpoint = el.getAttribute('data-action-endpoint');
          const actionType = el.getAttribute('data-branch-action');
          const campaignId = el.getAttribute('data-campaign-id') || '';
          if (!endpoint || !actionType) return;

          const body = new URLSearchParams({ action_type: actionType, campaign_id: campaignId });
          if (navigator.sendBeacon) {
            navigator.sendBeacon(endpoint, body);
          } else {
            fetch(endpoint, { method: 'POST', body, keepalive: true }).catch(() => {});
          }
        });
      });
    },

    async shareCanonical(title, canonicalUrl, triggerBtn) {
      // 1. Inside Telegram Mini App: use Telegram share URL handoff
      if (this.isTelegramNative && tg && typeof tg.openTelegramLink === 'function') {
        const tgShare = 'https://t.me/share/url?url=' + encodeURIComponent(canonicalUrl) + '&text=' + encodeURIComponent(title);
        tg.openTelegramLink(tgShare);
        return;
      }

      // 2. Web Share API where supported on mobile browsers
      if (navigator.share) {
        try {
          await navigator.share({ title, url: canonicalUrl });
          return;
        } catch (_) {}
      }

      // 3. Copy-link fallback (C-17 requirement)
      try {
        await navigator.clipboard.writeText(canonicalUrl);
        if (triggerBtn) {
          const orig = triggerBtn.innerHTML;
          triggerBtn.textContent = 'Link Copied!';
          setTimeout(() => { triggerBtn.innerHTML = orig; }, 2000);
        }
      } catch (_) {
        window.prompt('Copy canonical Bulbula.et link:', canonicalUrl);
      }
    }
  };

  window.BulbulaSurface = SurfaceAdapter;
  document.addEventListener('DOMContentLoaded', () => SurfaceAdapter.init());
})();
