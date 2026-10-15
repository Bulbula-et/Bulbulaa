/**
 * Bulbula.et unified Web / Telegram Mini App surface adapter (C-17, C-32).
 * Telegram launch data is capability context only and is never Customer identity.
 */
(function () {
  'use strict';

  const candidate = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;
  // The bridge also exists on ordinary Web pages. A Telegram-provided platform
  // indicates Mini App capability even when a keyboard/inline launch has empty
  // initData; launch data is never used as Customer authentication here.
  const platform = candidate && typeof candidate.platform === 'string' ? candidate.platform.toLowerCase() : '';
  const hasLaunchContext = (platform !== '' && platform !== 'unknown') ||
    (candidate && typeof candidate.initData === 'string' && candidate.initData.length > 0);
  const tg = candidate && hasLaunchContext ? candidate : null;

  const SurfaceAdapter = {
    isTelegramNative: Boolean(tg),

    init() {
      if (this.isTelegramNative) {
        document.documentElement.dataset.surface = 'telegram-mini-app';
        document.documentElement.dataset.telegramPlatform = String(tg.platform || 'unknown');
        try {
          if (typeof tg.ready === 'function') tg.ready();
          if (typeof tg.expand === 'function') tg.expand();
          if (typeof tg.setHeaderColor === 'function') tg.setHeaderColor('#FFFAF5');
          if (typeof tg.setBackgroundColor === 'function') tg.setBackgroundColor('#FFFAF5');
        } catch (_) {}
      } else {
        document.documentElement.dataset.surface = 'web';
      }

      document.querySelectorAll('.b-bottom-nav__item, .b-btn, .b-chip').forEach((el) => {
        el.addEventListener('click', () => this.hapticImpact('light'));
      });

      document.querySelectorAll('[data-bulbula-share], [data-share-btn]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
          event.preventDefault();
          const title = btn.getAttribute('data-share-title') || document.title;
          const relativeUrl = btn.getAttribute('data-share-url') || window.location.pathname;
          const canonicalUrl = new URL(relativeUrl, window.location.origin).toString();
          this.shareCanonical(title, canonicalUrl, btn);
        });
      });

      document.querySelectorAll('[data-branch-action]').forEach((el) => {
        el.addEventListener('click', () => {
          const endpoint = el.getAttribute('data-action-endpoint');
          const actionType = el.getAttribute('data-branch-action');
          const campaignId = el.getAttribute('data-campaign-id') || '';
          if (!endpoint || !actionType) return;
          const body = new URLSearchParams({ action_type: actionType, campaign_id: campaignId });
          if (navigator.sendBeacon) navigator.sendBeacon(endpoint, body);
          else fetch(endpoint, { method: 'POST', body, keepalive: true }).catch(() => {});
        });
      });

      document.dispatchEvent(new CustomEvent('bulbula:surface-ready', { detail: { surface: this.isTelegramNative ? 'TELEGRAM_MINI_APP' : 'WEB' } }));
    },

    hapticImpact(style) {
      if (!this.isTelegramNative || !tg.HapticFeedback || typeof tg.HapticFeedback.impactOccurred !== 'function') return;
      try { tg.HapticFeedback.impactOccurred(style); } catch (_) {}
    },

    async shareCanonical(title, canonicalUrl, triggerBtn) {
      if (this.isTelegramNative && typeof tg.openTelegramLink === 'function') {
        const shareUrl = 'https://t.me/share/url?url=' + encodeURIComponent(canonicalUrl) + '&text=' + encodeURIComponent(title);
        try {
          tg.openTelegramLink(shareUrl);
          this.hapticImpact('light');
          return;
        } catch (_) {}
      }

      if (navigator.share) {
        try {
          await navigator.share({ title, url: canonicalUrl });
          return;
        } catch (error) {
          if (error && error.name === 'AbortError') return;
        }
      }

      try {
        await navigator.clipboard.writeText(canonicalUrl);
        if (triggerBtn) {
          const original = triggerBtn.innerHTML;
          triggerBtn.textContent = 'Link Copied!';
          setTimeout(() => { triggerBtn.innerHTML = original; }, 2000);
        }
      } catch (_) {
        window.prompt('Copy canonical Bulbula.et link:', canonicalUrl);
      }
    }
  };

  window.BulbulaSurface = SurfaceAdapter;
  document.addEventListener('DOMContentLoaded', () => SurfaceAdapter.init());
})();
