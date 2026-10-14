<?php
declare(strict_types=1);
use Bulbula\Core\View;

$areas      = $areas ?? [];
$categories = $categories ?? [];
?>
<section class="b-section b-section--tint" style="padding: var(--space-8) 0;">
  <div class="b-container">
    <div class="b-section__eyebrow">Local Geography</div>
    <h1>Bole Bulbula Neighborhood Areas</h1>
    <p style="margin-top: var(--space-2);">
      Discover verified branches across every sub-locality and corridor of Bole Bulbula.
    </p>
  </div>
</section>

<section class="b-section">
  <div class="b-container">
    <div class="b-grid b-grid--areas">
      <?php foreach ($areas as $area): ?>
        <?php $areaImg = View::areaImage((string) ($area['slug'] ?? '')); ?>
        <article class="b-card" data-gsap="card">
          <a href="/a/<?= View::e($area['slug']) ?>" class="b-card__media" aria-label="<?= View::e($area['name_en']) ?>">
            <img src="<?= View::e($areaImg) ?>" alt="<?= View::e($area['name_en']) ?>" class="b-card__img" loading="lazy">
            <span class="b-card__float-pill">
              <span class="b-badge b-badge--orange b-tabular">
                <?= (int) ($area['published_branch_count'] ?? 0) ?> Branches
              </span>
            </span>
          </a>

          <div class="b-card__body">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <span class="b-card__category">Bole Bulbula Corridor</span>
              <?php if (!empty($area['center_lat']) && !empty($area['center_lng'])): ?>
                <a href="/nearby?lat=<?= View::e((string) $area['center_lat']) ?>&amp;lng=<?= View::e((string) $area['center_lng']) ?>&amp;radius=2000"
                   style="font-size: var(--text-xs); font-weight: 700; color: var(--orange-500);">
                  Nearby &rarr;
                </a>
              <?php endif; ?>
            </div>

            <h2 class="b-card__title" style="font-size: var(--text-lg);">
              <a href="/a/<?= View::e($area['slug']) ?>"><?= View::e($area['name_en']) ?></a>
            </h2>
            <?php if (!empty($area['name_am'])): ?>
              <div class="b-lang-am" lang="am" style="font-size: var(--text-xs); color: var(--neutral-500);">
                <?= View::e($area['name_am']) ?>
              </div>
            <?php endif; ?>

            <div style="margin-top: auto; padding-top: var(--space-3); border-top: 1px solid var(--color-border-subtle); display: flex; flex-wrap: wrap; gap: 0.35rem;">
              <?php foreach ($categories as $cat): ?>
                <a href="/a/<?= View::e($area['slug']) ?>/<?= View::e($cat['slug']) ?>" class="b-subcat-pill">
                  <?= View::e($cat['name_en']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
