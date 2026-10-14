/**
 * ============================================================================
 * BULBULA.ET V1 — PROGRESSIVE GSAP MOTION ENGINE (AI DESIGN OS SECTION 23 & 24)
 * - Progressive enhancement: HTML is 100% visible by default if JS/GSAP is absent.
 * - Respects `prefers-reduced-motion: reduce`.
 * - Animates hero hierarchy, branch/category cards, and drawers with restraint.
 * ============================================================================
 */
(function () {
  'use strict';

  function initMotion() {
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReduced || typeof window.gsap === 'undefined') {
      return;
    }

    const gsap = window.gsap;

    // 1. Hero Entrance Choreography
    const heroItems = document.querySelectorAll('[data-gsap="hero-item"]');
    if (heroItems.length > 0) {
      gsap.fromTo(
        heroItems,
        { y: 18, opacity: 0 },
        {
          y: 0,
          opacity: 1,
          duration: 0.55,
          stagger: 0.08,
          ease: 'power3.out',
          clearProps: 'transform,opacity'
        }
      );
    }

    // 2. Staggered Discovery Cards (Categories, Areas, Branches)
    const cards = document.querySelectorAll('[data-gsap="card"]');
    if (cards.length > 0) {
      gsap.fromTo(
        cards,
        { y: 16, opacity: 0 },
        {
          y: 0,
          opacity: 1,
          duration: 0.45,
          stagger: 0.045,
          ease: 'power2.out',
          clearProps: 'transform,opacity'
        }
      );
    }

    // 3. Subtle Radar Pulse on Telemetry Indicator
    const beacon = document.querySelector('[data-gsap="beacon"]');
    if (beacon) {
      gsap.to(beacon, {
        scale: 1.25,
        opacity: 0.65,
        duration: 1.4,
        repeat: -1,
        yoyo: true,
        ease: 'sine.inOut'
      });
    }
  }

  document.addEventListener('DOMContentLoaded', initMotion);
})();
