/**
 * ============================================================================
 * BULBULA.ET V1 — NEARBY & DISTANCE DISCOVERY MODULE (C-07)
 * Requests browser geolocation ONLY when visitor explicitly clicks "Use My Location"
 * and never persists visitor GPS coordinates on the server.
 * ============================================================================
 */
(function () {
  'use strict';

  function initNearby() {
    const locateBtn = document.querySelector('[data-nearby-locate]');
    const statusEl = document.querySelector('[data-nearby-status]');
    if (!locateBtn) return;

    locateBtn.addEventListener('click', () => {
      if (!navigator.geolocation) {
        if (statusEl) {
          statusEl.textContent = 'Geolocation is not supported by your browser. Please use one of the Bole Bulbula area presets or browse by area below.';
        }
        return;
      }

      locateBtn.disabled = true;
      locateBtn.textContent = 'Locating...';

      navigator.geolocation.getCurrentPosition(
        (pos) => {
          const lat = pos.coords.latitude.toFixed(6);
          const lng = pos.coords.longitude.toFixed(6);
          window.location.href = `/nearby?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}&radius=5000`;
        },
        () => {
          locateBtn.disabled = false;
          locateBtn.textContent = 'Use My Current Location';
          if (statusEl) {
            statusEl.textContent = 'Location access was declined or unavailable. You can use a Bole Bulbula landmark preset or browse by neighborhood area below.';
          }
        },
        { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }
      );
    });
  }

  document.addEventListener('DOMContentLoaded', initNearby);
})();
