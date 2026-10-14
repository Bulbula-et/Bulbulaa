<?php
declare(strict_types=1);
use Bulbula\Core\View;

$filters        = $filters ?? [];
$branches       = $branches ?? [];
$sponsoredItems = $sponsoredItems ?? [];
$categories     = $categories ?? [];
$areas          = $areas ?? [];
$q              = (string) ($filters['q'] ?? '');
$catFilter      = (string) ($filters['category_slug'] ?? $filters['category'] ?? '');
$areaFilter     = (string) ($filters['area_slug'] ?? $filters['area'] ?? '');
$sortFilter     = (string) ($filters['sort'] ?? 'organic');
?>

<!-- Clean Light-Orange Header Strip -->
<section class="b-section b-section--tint" style="padding: var(--space-8) 0;">
  <div class="b-container">
    <div class="b-section__eyebrow">Directory Search</div>
    <h1 style="margin-bottom: var(--space-4);">
      <?= $q !== '' ? 'Results for “' . View::e($q) . '”' : 'Explore Bole Bulbula Businesses' ?>
    </h1>

    <form action="/search" method="GET" class="b-card" style="padding: var(--space-4); border-radius: var(--radius-xl);">
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(175px, 1fr)); gap: var(--space-3); align-items: end;">
        <div>
          <label class="b-label" for="search-q">Keyword</label>
          <input id="search-q" type="search" name="q" value="<?= View::e($q) ?>" class="b-input" placeholder="Business, service, landmark...">
        </div>

        <div>
          <label class="b-label" for="search-cat">Category</label>
          <select id="search-cat" name="category" class="b-select">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= View::e($cat['slug']) ?>" <?= ($catFilter === $cat['slug']) ? 'selected' : '' ?>>
                <?= View::e($cat['name_en']) ?>
              </option>
              <?php foreach (($cat['subcategories'] ?? []) as $sub): ?>
                <option value="<?= View::e($sub['slug']) ?>" <?= ($catFilter === $sub['slug']) ? 'selected' : '' ?>>
                  &nbsp;&nbsp;&mdash; <?= View::e($sub['name_en']) ?>
                </option>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="b-label" for="search-area">Area</label>
          <select id="search-area" name="area" class="b-select">
            <option value="">All Areas</option>
            <?php foreach ($areas as $a): ?>
              <option value="<?= View::e($a['slug']) ?>" <?= ($areaFilter === $a['slug']) ? 'selected' : '' ?>>
                <?= View::e($a['name_en']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="b-label" for="search-sort">Sort Order</label>
          <select id="search-sort" name="sort" class="b-select">
            <option value="organic" <?= ($sortFilter === 'organic') ? 'selected' : '' ?>>Relevance &amp; Trust</option>
            <option value="rating" <?= ($sortFilter === 'rating') ? 'selected' : '' ?>>Highest Rated</option>
            <option value="alpha" <?= ($sortFilter === 'alpha') ? 'selected' : '' ?>>Alphabetical (A–Z)</option>
          </select>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
          <label style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: var(--text-xs); font-weight: 600; cursor: pointer;">
            <input type="checkbox" name="verified" value="1" <?= !empty($filters['verified_only']) ? 'checked' : '' ?>>
            <span>Verified</span>
          </label>
          <label style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: var(--text-xs); font-weight: 600; cursor: pointer;">
            <input type="checkbox" name="open_now" value="1" <?= !empty($filters['open_now']) ? 'checked' : '' ?>>
            <span>Open Now</span>
          </label>
          <button type="submit" class="b-btn b-btn--primary b-btn--sm" style="margin-left: auto;">Apply</button>
        </div>
      </div>
    </form>
    <div class="b-filter-pills" role="navigation" aria-label="Quick category filter" style="margin-top: var(--space-4); margin-bottom: var(--space-2);">
      <a href="/search" class="b-filter-pill <?= $catFilter === '' ? 'is-active' : '' ?>">All Branches</a>
      <a href="/search?category=clinics-medical-centers" class="b-filter-pill <?= $catFilter === 'clinics-medical-centers' ? 'is-active' : '' ?>">Clinics</a>
      <a href="/search?category=pharmacies" class="b-filter-pill <?= $catFilter === 'pharmacies' ? 'is-active' : '' ?>">Pharmacies</a>
      <a href="/search?category=cafes-coffee-roasters" class="b-filter-pill <?= $catFilter === 'cafes-coffee-roasters' ? 'is-active' : '' ?>">Coffee &amp; Cafes</a>
      <a href="/search?category=supermarkets" class="b-filter-pill <?= $catFilter === 'supermarkets' ? 'is-active' : '' ?>">Supermarkets</a>
      <a href="/search?category=dental-clinics" class="b-filter-pill <?= $catFilter === 'dental-clinics' ? 'is-active' : '' ?>">Dental Care</a>
      <a href="/search?category=auto-repair-garages" class="b-filter-pill <?= $catFilter === 'auto-repair-garages' ? 'is-active' : '' ?>">Auto Garages</a>
    </div>
  </div>
</section>

<section class="b-section">
  <div class="b-container">
    <?php if (!empty($sponsoredItems)): ?>
      <?= View::component('sponsored-slot', [
          'sponsoredItems' => $sponsoredItems,
          'slotTitle'      => 'Sponsored Placement',
      ]) ?>
    <?php endif; ?>

    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-5);">
      <span class="b-tabular" style="font-size: var(--text-sm); color: var(--neutral-600);">
        Showing <strong style="color: var(--neutral-900);"><?= count($branches) ?></strong> organic <?= count($branches) === 1 ? 'branch' : 'branches' ?>
      </span>
      <?php if ($q !== '' || $catFilter !== '' || $areaFilter !== '' || !empty($filters['verified_only']) || !empty($filters['open_now'])): ?>
        <a href="/search" style="font-size: var(--text-xs); font-weight: 600; color: var(--orange-600);">Reset Filters</a>
      <?php endif; ?>
    </div>

    <?php if (empty($branches)): ?>
      <div class="b-card" style="text-align: center; padding: var(--space-12);">
        <h3>No matching branches found</h3>
        <p style="max-width: 44ch; margin: var(--space-2) auto var(--space-5);">
          Try clearing one of your filters or browse all categories across Bole Bulbula.
        </p>
        <div style="display: flex; justify-content: center; gap: 0.75rem;">
          <a href="/search" class="b-btn b-btn--primary">View All Branches</a>
          <a href="/categories" class="b-btn b-btn--outline">Browse Categories</a>
        </div>
      </div>
    <?php else: ?>
      <div class="b-grid b-grid--branches">
        <?php foreach ($branches as $branch): ?>
          <?= View::component('branch-card', ['branch' => $branch]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
