<?php
declare(strict_types=1);
use Bulbula\Core\View;

$category       = $category ?? null;
$area           = $area ?? null;
if (!isset($mode)) {
    if ($area && $category) {
        $mode = 'area_category';
    } elseif ($area) {
        $mode = 'area';
    } else {
        $mode = 'category';
    }
}
$branches       = $branches ?? ($organicResults ?? []);
$sponsoredItems = $sponsoredItems ?? ($sponsoredResults ?? []);
$allCategories  = $allCategories ?? [];
$allAreas       = $allAreas ?? [];

$headingEn = '';
$headingAm = '';
$subtext   = '';
$bannerImg = View::asset('img/cards/hero-bole-bulbula-bg.jpg');

if ($mode === 'category' && $category) {
    $headingEn = $category['name_en'] . ' in Bole Bulbula';
    $headingAm = (string) ($category['name_am'] ?? '');
    $subtext   = (string) ($category['description_en'] ?? 'Verified local branches in Bole Bulbula.');
    $bannerImg = View::categoryImage((string) ($category['slug'] ?? ''));
} elseif ($mode === 'area' && $area) {
    $headingEn = $area['name_en'];
    $headingAm = (string) ($area['name_am'] ?? '');
    $subtext   = 'Verified local businesses and services operating in ' . $area['name_en'] . '.';
    $bannerImg = View::areaImage((string) ($area['slug'] ?? ''));
} elseif ($mode === 'area_category' && $area && $category) {
    $headingEn = $category['name_en'] . ' in ' . $area['name_en'];
    $headingAm = trim(($category['name_am'] ?? '') . ' — ' . ($area['name_am'] ?? ''), ' —');
    $subtext   = 'Staff-verified ' . strtolower((string) $category['name_en']) . ' serving ' . $area['name_en'] . ', Bole Bulbula.';
    $bannerImg = View::categoryImage((string) ($category['slug'] ?? ''));
}
?>

<section class="b-section" style="padding: var(--space-5) 0 var(--space-4);">
  <div class="b-container">
    <!-- Rounded Photographic Category/Area Banner -->
    <div style="position: relative; border-radius: 22px; overflow: hidden; padding: clamp(1.5rem, 3.5vw, 2.75rem); background: #141210; color: #FFFFFF; box-shadow: var(--shadow-lg);">
      <img src="<?= View::e($bannerImg) ?>" alt="<?= View::e($headingEn) ?>" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0.42;">
      <div style="position: absolute; inset: 0; background: linear-gradient(95deg, rgba(16,13,10,0.92) 0%, rgba(20,16,12,0.78) 55%, rgba(20,16,12,0.45) 100%);"></div>

      <div style="position: relative; z-index: 2; max-width: 680px;">
        <nav aria-label="Breadcrumb" style="font-size: var(--text-xs); color: rgba(255,255,255,0.72); margin-bottom: var(--space-2);">
          <a href="/" style="color: rgba(255,255,255,0.85);">Home</a>
          <?php if ($category): ?> &rsaquo; <a href="/categories" style="color: rgba(255,255,255,0.85);">Categories</a> <?php endif; ?>
          <?php if ($area): ?> &rsaquo; <a href="/areas" style="color: rgba(255,255,255,0.85);">Areas</a> <?php endif; ?>
          &rsaquo; <span style="color: #F28538; font-weight: 700;"><?= View::e($headingEn) ?></span>
        </nav>

        <h1 style="color: #FFFFFF; font-size: clamp(1.45rem, 3vw, 2.35rem);"><?= View::e($headingEn) ?></h1>
        <?php if ($headingAm !== ''): ?>
          <div class="b-lang-am" lang="am" style="font-size: var(--text-base); color: rgba(255,255,255,0.78); margin-top: 0.15rem;">
            <?= View::e($headingAm) ?>
          </div>
        <?php endif; ?>
        <p style="margin-top: var(--space-2); color: rgba(255,255,255,0.84); font-size: var(--text-sm);"><?= View::e($subtext) ?></p>

        <!-- Cross-Filter Pills -->
        <div style="display: flex; flex-wrap: wrap; gap: 0.45rem; margin-top: var(--space-4);">
          <?php if ($mode === 'category' && $category): ?>
            <?php foreach ($allAreas as $a): ?>
              <a href="/a/<?= View::e($a['slug']) ?>/<?= View::e($category['slug']) ?>" class="b-chip">
                In <?= View::e($a['name_en']) ?>
              </a>
            <?php endforeach; ?>
          <?php elseif ($mode === 'area' && $area): ?>
            <?php foreach ($allCategories as $c): ?>
              <a href="/a/<?= View::e($area['slug']) ?>/<?= View::e($c['slug']) ?>" class="b-chip">
                <?= View::e($c['name_en']) ?>
              </a>
            <?php endforeach; ?>
          <?php elseif ($mode === 'area_category' && $area && $category): ?>
            <a href="/c/<?= View::e($category['slug']) ?>" class="b-chip">All <?= View::e($category['name_en']) ?></a>
            <a href="/a/<?= View::e($area['slug']) ?>" class="b-chip">All in <?= View::e($area['name_en']) ?></a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="b-section" style="padding-top: var(--space-4);">
  <div class="b-container">
    <?php if (!empty($sponsoredItems)): ?>
      <?= View::component('sponsored-slot', [
          'sponsoredItems' => $sponsoredItems,
          'slotTitle'      => 'Featured in ' . $headingEn,
      ]) ?>
    <?php endif; ?>

    <?php if (empty($branches)): ?>
      <div class="b-card" style="text-align: center; padding: var(--space-10);">
        <h3>No branches listed yet in this slice</h3>
        <p style="margin: var(--space-2) auto var(--space-4);">Explore adjacent Bole Bulbula neighborhoods or browse all categories.</p>
        <a href="/search" class="b-btn b-btn--primary">Browse All Branches</a>
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
