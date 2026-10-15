<?php
declare(strict_types=1);
use Bulbula\Core\View;

/**
 * Unified Futuristic Floating Bottom Navigation Dock.
 * Works identically across Mobile Web, Telegram Mini App (Mobile & Desktop),
 * Tablet, and Desktop Web — 1 single responsive UI codebase.
 */
$currentPath = isset($request) ? $request->path() : '/';

$isDiscover   = ($currentPath === '/');
$isCategories = (str_starts_with($currentPath, '/categories') || str_starts_with($currentPath, '/c/') || str_starts_with($currentPath, '/areas') || str_starts_with($currentPath, '/a/'));
$isSearch     = str_starts_with($currentPath, '/search');
$isNearby     = str_starts_with($currentPath, '/nearby');
$isAccount    = (str_starts_with($currentPath, '/account') || str_starts_with($currentPath, '/auth'));
?>
<nav class="b-bottom-nav" aria-label="App Bottom Navigation">
  <div class="b-bottom-nav__dock">
    <!-- 1. Discover (Home) -->
    <a href="/" class="b-bottom-nav__item <?= $isDiscover ? 'is-active' : '' ?>" aria-current="<?= $isDiscover ? 'page' : 'false' ?>">
      <span class="b-bottom-nav__icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 10.5L12 3l9 7.5V20a1 1 0 0 1-1 1h-5.5v-6h-5v6H4a1 1 0 0 1-1-1v-9.5z"/>
        </svg>
      </span>
      <span class="b-bottom-nav__label">Discover</span>
    </a>

    <!-- 2. Categories & Areas -->
    <a href="/categories" class="b-bottom-nav__item <?= $isCategories ? 'is-active' : '' ?>" aria-current="<?= $isCategories ? 'page' : 'false' ?>">
      <span class="b-bottom-nav__icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="7" height="7" rx="2"/>
          <rect x="14" y="3" width="7" height="7" rx="2"/>
          <rect x="3" y="14" width="7" height="7" rx="2"/>
          <rect x="14" y="14" width="7" height="7" rx="2"/>
        </svg>
      </span>
      <span class="b-bottom-nav__label">Categories</span>
    </a>

    <!-- 3. Center Futuristic Spotlight Search Orb -->
    <a href="/search" class="b-bottom-nav__item b-bottom-nav__item--center <?= $isSearch ? 'is-active' : '' ?>" aria-label="Search Directory" aria-current="<?= $isSearch ? 'page' : 'false' ?>">
      <span class="b-bottom-nav__orb" aria-hidden="true">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="7"/>
          <path d="M20 20l-3.8-3.8"/>
        </svg>
      </span>
      <span class="b-bottom-nav__label">Search</span>
    </a>

    <!-- 4. Nearby Radar -->
    <a href="/nearby?lat=8.9538&amp;lng=38.7892&amp;radius=3500" class="b-bottom-nav__item <?= $isNearby ? 'is-active' : '' ?>" aria-current="<?= $isNearby ? 'page' : 'false' ?>">
      <span class="b-bottom-nav__icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 21s-7-5.686-7-11a7 7 0 1 1 14 0c0 5.314-7 11-7 11z"/>
          <circle cx="12" cy="10" r="2.5"/>
        </svg>
      </span>
      <span class="b-bottom-nav__label">Nearby</span>
    </a>

    <!-- 5. Account (Saved places remain inside Account settings) -->
    <a href="<?= !empty($customer) ? '/account' : '/auth/login' ?>" class="b-bottom-nav__item <?= $isAccount ? 'is-active' : '' ?>" aria-current="<?= $isAccount ? 'page' : 'false' ?>">
      <span class="b-bottom-nav__icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="8" r="3.2"/>
          <path d="M5.5 20v-1.5a6.5 6.5 0 0 1 13 0V20"/>
        </svg>
      </span>
      <span class="b-bottom-nav__label">Account</span>
    </a>
  </div>
</nav>
