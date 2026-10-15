<?php
declare(strict_types=1);
use Bulbula\Core\View;

/**
 * C-01 Homepage — Cloned Multi-Screen & Wide-Web Layout:
 * 1. Top Search + Square Filter Button & Framed Scenic Hero Banner + 3 Quick Action Tiles (Images 1, 2 & 3)
 * 2. "Why Bole Bulbula Residents Choose Bulbula.et" 2-Col Section with 3 Circular Stats + 3 Stacked Feature Cards (Image 3 WANDER.ph)
 * 3. Tinted Rounded "Top Categories" Container with 2-in-a-Row Mobile / 4-in-a-Row Desktop Full-Bleed Blended Cards (Image 1 Screen 2 & Image 3)
 * 4. "Featured Verified Branches" 2-in-a-Row Mobile / 4-in-a-Row Desktop Visual Cards (Image 1 & Image 2)
 * 5. "Top Branches Near You" Filter Pills + Horizontal Media-Left Vendor Rows (Image 2 Right Screen & Image 1 Middle Screens)
 * 6. 3-Col Asymmetric "Neighborhood Corridors" Bento Showcase (Bottom of Image 3 WANDER.ph)
 */
$categories    = $categories ?? [];
$areas         = $areas ?? [];
$branches      = $branches ?? [];
$sponsoredHome = $sponsoredHome ?? [];
?>

<!-- ==========================================================================
     SECTION 1: BULBULA.ET HERO REDESIGN + 4-PILLAR NEIGHBORHOOD STRIP
     (Cloned from bulbula_hero_redesign.png across Wide & Small Screens)
     ========================================================================== -->
<section class="b-hero-section">
  <div class="b-container b-hero-section__container">
    <!-- Framed Rounded Sunset Hero Box -->
    <div class="b-wander-hero">
      <img src="<?= View::asset('img/cards/hero-bole-bulbula-bg.jpg') ?>" alt="Bole Bulbula, Addis Ababa Sunset Boulevard" class="b-wander-hero__bg">
      <div class="b-wander-hero__overlay"></div>

      <!-- Left Editorial & Search Column -->
      <div class="b-hero-redesign__left">
        <div class="b-hero-redesign__eyebrow" data-gsap="hero-item">
          <span>LOCAL PLACES. REAL PEOPLE.</span>
          <span class="b-hero-redesign__bar"></span>
        </div>

        <h1 class="b-hero-redesign__headline" data-gsap="hero-item">
          Find your next<br>
          favorite place in<br>
          <span class="b-hero-redesign__accent">Bole Bulbula.</span>
        </h1>

        <p class="b-hero-redesign__sub" data-gsap="hero-item">
          Discover trusted cafés, clinics, pharmacies, and<br class="b-hide-mobile">
          everyday services near you.
        </p>

        <!-- Unified White Pill Search Bar Inside Hero (C-01, C-02, C-03) -->
        <div class="b-hero-search-wrap" data-autocomplete-dock data-gsap="hero-item">
          <form action="/search" method="GET" class="b-hero-search-pill" role="search">
            <div class="b-hero-search-pill__field">
              <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#525860" stroke-width="2.2" aria-hidden="true" style="flex-shrink: 0;">
                <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
              </svg>
              <input
                type="search"
                name="q"
                class="b-hero-search-pill__input"
                placeholder="Search cafés, clinics, pharmacies..."
                autocomplete="off"
                aria-label="Search cafés, clinics, pharmacies"
                data-autocomplete-input
              >
            </div>

            <div class="b-hero-search-pill__area">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#111315" stroke-width="2.3" aria-hidden="true" style="flex-shrink: 0;">
                <path d="M12 21s-7-5.686-7-11a7 7 0 1 1 14 0c0 5.314-7 11-7 11z"/>
                <circle cx="12" cy="10" r="2.5" fill="#111315"/>
              </svg>
              <select name="area" class="b-hero-search-pill__select" aria-label="Select neighborhood area">
                <option value="">Bole Bulbula</option>
                <?php foreach ($areas as $a): ?>
                  <option value="<?= View::e($a['slug']) ?>"><?= View::e($a['name_en']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <button type="submit" class="b-hero-search-pill__btn" aria-label="Search">
              <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
              </svg>
            </button>
          </form>

          <!-- C-03 Instant Autocomplete Dropdown -->
          <div class="b-autocomplete" data-autocomplete-dropdown role="listbox" aria-label="Search suggestions"></div>
        </div>

        <!-- Two Pill Action Buttons: "Explore nearby ->" & "Browse categories ->" -->
        <div class="b-hero-redesign__actions" data-gsap="hero-item">
          <a href="/nearby?lat=8.9538&amp;lng=38.7892&amp;radius=3500" class="b-hero-cta b-hero-cta--orange">
            <span>Explore nearby</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 12h14M13 6l6 6-6 6"/>
            </svg>
          </a>

          <a href="/categories" class="b-hero-cta b-hero-cta--outline">
            <span>Browse categories</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 12h14M13 6l6 6-6 6"/>
            </svg>
          </a>
        </div>

        <!-- Bottom Trust Micro-Row: 120+ local places • Open now • Verified listings -->
        <div class="b-hero-redesign__trust" data-gsap="hero-item">
          <span class="b-hero-redesign__trust-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
              <path d="M9 12l2 2 4-4"/>
            </svg>
            <span>120+ local places</span>
          </span>
          <span class="b-hero-redesign__trust-dot">&bull;</span>
          <span class="b-hero-redesign__trust-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            </svg>
            <span>Open now</span>
          </span>
          <span class="b-hero-redesign__trust-dot">&bull;</span>
          <span class="b-hero-redesign__trust-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <span>Verified listings</span>
          </span>
        </div>
      </div>

      <!-- Interactive Clickable Map Hotspots on Right Side of Hero (Desktop) -->
      <div class="b-hero-map-hotspots" aria-label="Bole Bulbula Map Quick Links">
        <a href="/areas" class="b-hero-hotspot b-hero-hotspot--loc" title="Explore Bole Bulbula, Addis Ababa">
          <span class="sr-only">Bole Bulbula, Addis Ababa</span>
          <span class="b-hero-hotspot__tip">Explore 4 Corridors &rarr;</span>
        </a>
        <a href="/c/cafes-coffee-roasters" class="b-hero-hotspot b-hero-hotspot--coffee" title="Explore Coffee &amp; Cafés in Bole Bulbula">
          <span class="sr-only">Coffee</span>
          <span class="b-hero-hotspot__tip">Coffee &amp; Roasteries &rarr;</span>
        </a>
        <a href="/c/health-medical" class="b-hero-hotspot b-hero-hotspot--health" title="Explore Healthcare &amp; Clinics in Bole Bulbula">
          <span class="sr-only">Healthcare</span>
          <span class="b-hero-hotspot__tip">Clinics &amp; Pharmacies &rarr;</span>
        </a>
        <a href="/c/home-auto-services" class="b-hero-hotspot b-hero-hotspot--services" title="Explore Local Services in Bole Bulbula">
          <span class="sr-only">Services</span>
          <span class="b-hero-hotspot__tip">Auto &amp; Home Services &rarr;</span>
        </a>
      </div>
    </div>

    <!-- Bottom 4-Pillar Trust & Value Strip (Cloned from bottom of bulbula_hero_redesign.png) -->
    <div class="b-hero-pillars">
      <a href="/search" class="b-hero-pillar">
        <span class="b-hero-pillar__icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l1.5-5h15L21 9"/>
            <path d="M3 9c0 1.657 1.343 3 3 3s3-1.343 3-3c0 1.657 1.343 3 3 3s3-1.343 3-3c0 1.657 1.343 3 3 3s3-1.343 3-3"/>
            <path d="M5 12v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8"/>
          </svg>
        </span>
        <div>
          <div class="b-hero-pillar__title">Local Businesses</div>
          <div class="b-hero-pillar__sub">Cafés, shops and more</div>
        </div>
      </a>

      <a href="/policies/verification" class="b-hero-pillar">
        <span class="b-hero-pillar__icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
          </svg>
        </span>
        <div>
          <div class="b-hero-pillar__title">Trusted Listings</div>
          <div class="b-hero-pillar__sub">Real places, verified</div>
        </div>
      </a>

      <a href="/areas" class="b-hero-pillar">
        <span class="b-hero-pillar__icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 21s-7-5.686-7-11a7 7 0 1 1 14 0c0 5.314-7 11-7 11z"/>
            <circle cx="12" cy="10" r="2.5"/>
          </svg>
        </span>
        <div>
          <div class="b-hero-pillar__title">In Your Neighborhood</div>
          <div class="b-hero-pillar__sub">Bole Bulbula and nearby</div>
        </div>
      </a>

      <a href="/about" class="b-hero-pillar">
        <span class="b-hero-pillar__icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/>
            <circle cx="10" cy="7" r="4"/>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        </span>
        <div>
          <div class="b-hero-pillar__title">Support Local</div>
          <div class="b-hero-pillar__sub">A stronger community</div>
        </div>
      </a>
    </div>
  </div>
</section>

<!-- ==========================================================================
     SECTION 2: "WHY CHOOSE BULBULA.ET" 2-COL TRUST & STACKED FEATURE CARDS
     (Cloned from Image 3 WANDER.ph Middle Section)
     ========================================================================== -->
<section class="b-section" style="padding-top: var(--space-4);">
  <div class="b-container">
    <div class="b-why-grid">
      <!-- Left Column: Editorial Headline + 3 Circular Stat Badges -->
      <div>
        <div class="b-section__eyebrow">Company-Operated Directory</div>
        <h2 style="font-size: clamp(1.45rem, 2.6vw, 2.15rem); line-height: 1.18;">
          Why Residents Choose Bulbula.et for Everyday Local Discovery
        </h2>
        <p style="margin-top: var(--space-3); font-size: var(--text-sm); color: var(--neutral-600);">
          From 93 Mazoria to Mariam Mazoria, our Addis Ababa operations team verifies every branch location, phone number, and opening schedule so you never waste a trip.
        </p>

        <div class="b-why-stats">
          <div>
            <div class="b-why-stat__circle" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/>
              </svg>
            </div>
            <div class="b-why-stat__num b-tabular">100%</div>
            <div class="b-why-stat__label">Staff-Verified Listings</div>
          </div>

          <div>
            <div class="b-why-stat__circle" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>
              </svg>
            </div>
            <div class="b-why-stat__num b-tabular">EAT</div>
            <div class="b-why-stat__label">Live Opening Hours</div>
          </div>

          <div>
            <div class="b-why-stat__circle" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M12 21s-7-5.686-7-11a7 7 0 1 1 14 0c0 5.314-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>
              </svg>
            </div>
            <div class="b-why-stat__num b-tabular"><?= count($areas) ?>+</div>
            <div class="b-why-stat__label">Bole Bulbula Corridors</div>
          </div>
        </div>
      </div>

      <!-- Right Column: 3 Stacked Horizontal Feature Cards -->
      <div class="b-why-features">
        <div class="b-feature-pill-card">
          <div class="b-feature-pill-card__icon" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--orange-500)" stroke-width="2.2">
              <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
              <line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/>
            </svg>
          </div>
          <div>
            <h3 style="font-size: var(--text-base);">Local Landmark Precision</h3>
            <p style="font-size: var(--text-xs); color: var(--neutral-600); margin-top: 0.2rem;">
              Every branch profile includes familiar Bole Bulbula landmarks and direct map coordinates.
            </p>
          </div>
        </div>

        <div class="b-feature-pill-card">
          <div class="b-feature-pill-card__icon" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--orange-500)" stroke-width="2.2">
              <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
          </div>
          <div>
            <h3 style="font-size: var(--text-base);">Real-Time Open / Closed Status</h3>
            <p style="font-size: var(--text-xs); color: var(--neutral-600); margin-top: 0.2rem;">
              Computed live in Addis Ababa time (EAT), including 24/7 emergency clinics and pharmacies.
            </p>
          </div>
        </div>

        <div class="b-feature-pill-card">
          <div class="b-feature-pill-card__icon" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--orange-500)" stroke-width="2.2">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
            </svg>
          </div>
          <div>
            <h3 style="font-size: var(--text-base);">Direct One-Tap Branch Contact</h3>
            <p style="font-size: var(--text-xs); color: var(--neutral-600); margin-top: 0.2rem;">
              Call the branch desk directly, open Telegram chat, or launch turn-by-turn directions immediately.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ==========================================================================
     SECTION 3: TINTED ROUNDED "TOP CATEGORIES" CONTAINER WITH FULL-BLEED CARDS
     (Cloned from Image 1 Screen 2 "Services" 2-Col & Image 3 "Top Destinations" 4-Col)
     ========================================================================== -->
<section class="b-section" style="padding-top: 0;">
  <div class="b-container">
    <div class="b-destinations-box">
      <div class="b-section__header" style="margin-bottom: var(--space-5);">
        <div>
          <h2 style="font-size: clamp(1.35rem, 2.4vw, 1.85rem);">Top Categories</h2>
        </div>
        <p style="font-size: var(--text-xs); color: var(--neutral-600); max-width: 44ch;">
          From medical clinics and pharmacies to specialty coffee roasters and supermarkets across Bole Bulbula.
        </p>
      </div>

      <!-- 2 in a row on Mobile (< 640px), 3 on Tablet, 4 on Desktop -->
      <div class="b-grid b-grid--categories">
        <?php foreach ($categories as $cat): ?>
          <a href="/c/<?= View::e($cat['slug']) ?>" class="b-dest-card" data-gsap="card">
            <img src="<?= View::e(View::categoryImage((string) $cat['slug'])) ?>" alt="<?= View::e($cat['name_en']) ?>" class="b-dest-card__img" loading="lazy">
            <div class="b-dest-card__shade"></div>

            <div class="b-dest-card__top">
              <span class="b-dest-card__pill b-tabular">
                <?= (int) ($cat['published_branch_count'] ?? 0) ?> <?= ((int) ($cat['published_branch_count'] ?? 0) === 1) ? 'Branch' : 'Branches' ?>
              </span>
            </div>

            <div class="b-dest-card__bottom">
              <div class="b-dest-card__title"><?= View::e($cat['name_en']) ?></div>
              <?php if (!empty($cat['name_am'])): ?>
                <div class="b-dest-card__amharic b-lang-am" lang="am"><?= View::e($cat['name_am']) ?></div>
              <?php endif; ?>
              <?php if (!empty($cat['description_en'])): ?>
                <span class="b-dest-card__desc"><?= View::e($cat['description_en']) ?></span>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <div style="display: flex; align-items: center; justify-content: space-between; margin-top: var(--space-5);">
        <a href="/categories" class="b-btn b-btn--secondary b-btn--sm">View all categories</a>
        <span style="font-size: var(--text-xs); color: var(--neutral-600);">Bole Bulbula Taxonomy</span>
      </div>
    </div>
  </div>
</section>

<!-- ==========================================================================
     SECTION 4: FEATURED VERIFIED BRANCHES (2-IN-A-ROW MOBILE, 4-COL DESKTOP)
     (Cloned from Image 1 "Featured Products"/"Seeds" & Image 2 "Featured Events")
     ========================================================================== -->
<section class="b-section" style="padding-top: 0;">
  <div class="b-container">
    <?php if (!empty($sponsoredHome)): ?>
      <?= View::component('sponsored-slot', [
          'sponsoredItems' => $sponsoredHome,
          'slotTitle'      => 'Featured in Bole Bulbula',
      ]) ?>
    <?php endif; ?>

    <div class="b-section__header" style="margin-bottom: var(--space-4);">
      <div>
        <h2 style="font-size: clamp(1.25rem, 2.2vw, 1.75rem);">Featured Branches</h2>
      </div>
      <a href="/search" style="font-size: var(--text-xs); font-weight: 600; color: var(--orange-500);">See All &rsaquo;</a>
    </div>

    <div class="b-grid b-grid--branches">
      <?php foreach ($branches as $branch): ?>
        <?= View::component('branch-card', ['branch' => $branch]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ==========================================================================
     SECTION 5: "TOP BRANCHES NEAR YOU" — FILTER PILLS + HORIZONTAL VENDOR ROWS
     (Cloned from Image 2 Right Screen "Top Vendors Near You" & Image 1 Middle)
     ========================================================================== -->
<section class="b-section" style="padding-top: 0;">
  <div class="b-container">
    <div class="b-section__header" style="margin-bottom: var(--space-3);">
      <div>
        <h2 style="font-size: clamp(1.25rem, 2.2vw, 1.75rem);">Top Branches Near You</h2>
      </div>
      <a href="/nearby?lat=8.9538&amp;lng=38.7892&amp;radius=3500" style="font-size: var(--text-xs); font-weight: 600; color: var(--orange-500);">See All &rsaquo;</a>
    </div>

    <!-- Horizontal Scrollable Category Filter Pills (Live Instant Filter + Fallback Href) -->
    <div class="b-filter-pills" role="tablist" aria-label="Quick category filter">
      <a href="/search" class="b-filter-pill is-active" data-vendor-filter="all" role="tab" aria-selected="true">All Branches</a>
      <a href="/c/clinics-medical-centers" class="b-filter-pill" data-vendor-filter="clinics-medical-centers" role="tab" aria-selected="false">Clinics</a>
      <a href="/c/pharmacies" class="b-filter-pill" data-vendor-filter="pharmacies" role="tab" aria-selected="false">Pharmacies</a>
      <a href="/c/cafes-coffee-roasters" class="b-filter-pill" data-vendor-filter="cafes-coffee-roasters" role="tab" aria-selected="false">Coffee &amp; Cafes</a>
      <a href="/c/supermarkets" class="b-filter-pill" data-vendor-filter="supermarkets" role="tab" aria-selected="false">Supermarkets</a>
      <a href="/c/dental-clinics" class="b-filter-pill" data-vendor-filter="dental-clinics" role="tab" aria-selected="false">Dental Care</a>
      <a href="/c/auto-repair-garages" class="b-filter-pill" data-vendor-filter="auto-repair-garages" role="tab" aria-selected="false">Auto Garages</a>
    </div>

    <!-- Horizontal Media-Left Vendor Rows -->
    <div class="b-vendor-list" data-vendor-list>
      <?php foreach ($branches as $b): ?>
        <?php
          $url = $b['canonical_url'] ?? ('/b/' . ($b['business_slug'] ?? '') . '/' . ($b['branch_slug'] ?? ''));
          $coverImg = View::branchImage($b);
          $catSlugAttr = (string) ($b['primary_category_slug'] ?? '');
        ?>
        <article class="b-vendor-row" data-gsap="card" data-vendor-category="<?= View::e($catSlugAttr) ?>">
          <a href="<?= View::e($url) ?>" class="b-vendor-row__thumb" aria-label="<?= View::e($b['branch_name_en'] ?? '') ?>">
            <img src="<?= View::e($coverImg) ?>" alt="<?= View::e($b['branch_name_en'] ?? '') ?>" loading="lazy">
          </a>

          <div style="min-width: 0;">
            <span class="b-vendor-row__tag"><?= View::e($b['primary_category_name_en'] ?? 'Verified') ?></span>
            <h3 class="b-vendor-row__title">
              <a href="<?= View::e($url) ?>"><?= View::e($b['branch_name_en'] ?? $b['business_name_en'] ?? '') ?></a>
            </h3>
            <?php if (!empty($b['brand_summary_en'])): ?>
              <p class="b-card__summary" style="margin-top: 0.15rem;"><?= View::e($b['brand_summary_en']) ?></p>
            <?php endif; ?>
            <div class="b-vendor-row__loc">
              &#9679; <?= View::e($b['area_name_en'] ?? 'Bole Bulbula') ?>
              <?php if (!empty($b['open_now']['badge_label'])): ?>
                &middot; <strong style="color: var(--status-open-text);"><?= View::e($b['open_now']['badge_label']) ?></strong>
              <?php endif; ?>
            </div>
          </div>

          <div class="b-vendor-row__right">
            <?php if (!empty($b['published_review_count']) && (int) $b['published_review_count'] > 0): ?>
              <span class="b-rating b-tabular">
                <span class="b-rating__star">&#9733;</span>
                <span><?= number_format((float) $b['published_rating_avg'], 1) ?></span>
                <span style="color: var(--neutral-500); font-weight: 400;">(<?= (int) $b['published_review_count'] ?>)</span>
              </span>
            <?php else: ?>
              <span class="b-badge b-badge--verified">Verified</span>
            <?php endif; ?>

            <?php $vendorSaved = !empty($b['is_saved']); ?>
            <form action="/account/saved/toggle" method="POST" class="b-save-form" data-save-form>
              <?= View::csrfField() ?>
              <input type="hidden" name="branch_id" value="<?= (int) ($b['branch_id'] ?? $b['id'] ?? 0) ?>">
              <input type="hidden" name="business_id" value="<?= (int) ($b['business_id'] ?? 0) ?>">
              <input type="hidden" name="return_to" value="/">
              <button type="submit" class="b-save-toggle<?= $vendorSaved ? ' is-saved' : '' ?>" data-save-toggle aria-pressed="<?= $vendorSaved ? 'true' : 'false' ?>" aria-label="<?= $vendorSaved ? 'Remove ' : 'Save ' ?><?= View::e($b['branch_name_en'] ?? 'this place') ?><?= $vendorSaved ? ' from saved places' : '' ?>" title="<?= $vendorSaved ? 'Remove from saved places' : 'Save place' ?>">
                <svg class="b-save-toggle__icon" viewBox="0 0 24 24" aria-hidden="true" data-lucide="bookmark"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                <span class="b-save-toggle__burst" aria-hidden="true"></span>
              </button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ==========================================================================
     SECTION 6: 3-COL ASYMMETRIC "NEIGHBORHOOD CORRIDORS" SHOWCASE
     (Cloned from Bottom of Image 3 WANDER.ph "Tour Packages")
     ========================================================================== -->
<section class="b-section" style="padding-top: 0;">
  <div class="b-container">
    <div class="b-packages-grid">
      <!-- Column 1: Tinted Summary CTA Card -->
      <div class="b-package-cta-card">
        <div>
          <div class="b-section__eyebrow">Local Geography</div>
          <h2 style="font-size: var(--text-2xl); margin-top: 0.25rem;">Bole Bulbula Corridors</h2>
          <p style="font-size: var(--text-sm); color: var(--neutral-700); margin-top: var(--space-2);">
            Explore verified clinics, cafes, and everyday services organized by neighborhood sub-area across Bole Bulbula.
          </p>
        </div>
        <div>
          <a href="/areas" class="b-btn b-btn--secondary b-btn--sm">Browse all areas</a>
        </div>
      </div>

      <!-- Columns 2 & 3: Tall Full-Bleed Visual Area Cards -->
      <?php
        $areaVisuals = [
            0 => 'img/cards/photo-area-93-mazoria.jpg',
            1 => 'img/cards/photo-area-mariam.jpg',
        ];
      ?>
      <?php foreach (array_slice($areas, 0, 2) as $idx => $area): ?>
        <a href="/a/<?= View::e($area['slug']) ?>" class="b-dest-card" style="min-height: 260px;">
          <img src="<?= View::asset($areaVisuals[$idx] ?? 'img/cards/photo-area-93-mazoria.jpg') ?>" alt="<?= View::e($area['name_en']) ?>" class="b-dest-card__img" loading="lazy">
          <div class="b-dest-card__shade"></div>

          <div class="b-dest-card__top" style="justify-content: space-between; align-items: center;">
            <span style="width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.22); backdrop-filter: blur(8px); display: inline-flex; align-items: center; justify-content: center; color: #FFFFFF;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M12 21s-7-5.686-7-11a7 7 0 1 1 14 0c0 5.314-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>
              </svg>
            </span>
            <span class="b-dest-card__pill b-tabular">
              <?= (int) ($area['published_branch_count'] ?? 0) ?> Branches
            </span>
          </div>

          <div class="b-dest-card__bottom">
            <div class="b-dest-card__title"><?= View::e($area['name_en']) ?></div>
            <?php if (!empty($area['name_am'])): ?>
              <div class="b-dest-card__amharic b-lang-am" lang="am"><?= View::e($area['name_am']) ?></div>
            <?php endif; ?>
            <span class="b-dest-card__desc">
              Explore verified clinics, pharmacies, coffee roasters, and supermarkets serving <?= View::e($area['name_en']) ?>.
            </span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
