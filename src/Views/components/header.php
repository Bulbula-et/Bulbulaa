<?php
declare(strict_types=1);
use Bulbula\Core\View;

$currentPath = isset($request) ? $request->path() : '/';
?>
<!-- Single Frosted-Glass Apple-Style Header -->
<header class="b-header">
  <div class="b-container b-header__inner">
    <a href="/" class="b-logo-link" aria-label="Bulbula.et Home">
      <img src="<?= View::asset('img/logo-temp.svg') ?>" alt="Bulbula.et" class="b-logo-img">
    </a>

    <nav class="b-nav" aria-label="Primary Navigation">
      <a href="/" class="b-nav__link <?= $currentPath === '/' ? 'is-active' : '' ?>" <?= $currentPath === '/' ? 'aria-current="page"' : '' ?>>Discover</a>
      <a href="/categories" class="b-nav__link <?= (str_starts_with($currentPath, '/categories') || str_starts_with($currentPath, '/c/')) ? 'is-active' : '' ?>" <?= (str_starts_with($currentPath, '/categories') || str_starts_with($currentPath, '/c/')) ? 'aria-current="page"' : '' ?>>Categories</a>
      <a href="/areas" class="b-nav__link <?= (str_starts_with($currentPath, '/areas') || str_starts_with($currentPath, '/a/')) ? 'is-active' : '' ?>" <?= (str_starts_with($currentPath, '/areas') || str_starts_with($currentPath, '/a/')) ? 'aria-current="page"' : '' ?>>Areas</a>
      <a href="/nearby" class="b-nav__link <?= str_starts_with($currentPath, '/nearby') ? 'is-active' : '' ?>" <?= str_starts_with($currentPath, '/nearby') ? 'aria-current="page"' : '' ?>>Nearby</a>
      <a href="/about" class="b-nav__link <?= (str_starts_with($currentPath, '/about') || str_starts_with($currentPath, '/policies')) ? 'is-active' : '' ?>" <?= (str_starts_with($currentPath, '/about') || str_starts_with($currentPath, '/policies')) ? 'aria-current="page"' : '' ?>>About</a>
    </nav>

    <div class="b-header__right">
      <!-- Desktop-Only Compact Search Pill (Cloned from WANDER.ph Top Header Bar) -->
      <form action="/search" method="GET" class="b-header__search" role="search">
        <input
          type="search"
          name="q"
          class="b-header__search-input"
          placeholder="Search for a place, clinic, or cafe..."
          aria-label="Search directory"
        >
        <button type="submit" class="b-header__search-btn" aria-label="Search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
          </svg>
        </button>
      </form>

      <?php if (!empty($customer)): ?>
        <a href="/account" class="b-btn b-btn--outline b-btn--sm <?= str_starts_with($currentPath, '/account') ? 'is-current' : '' ?>" <?= str_starts_with($currentPath, '/account') ? 'aria-current="page"' : '' ?>>
          <span><?= View::e($customer['display_name']) ?></span>
        </a>
      <?php else: ?>
        <a href="/auth/login" class="b-btn b-btn--primary b-btn--sm">
          <span>Sign In</span>
        </a>
      <?php endif; ?>
    </div>
  </div>
</header>
