<?php
declare(strict_types=1);
use Bulbula\Core\View;

/**
 * C-04 Categories Page — Cloned Full-Bleed Blended Category Cards (Image 1 Screen 2 "Services" & Image 3 "Top Destinations")
 */
$categories = $categories ?? [];
$areas      = $areas ?? [];
?>
<section class="b-section" style="padding-top: var(--space-6);">
  <div class="b-container">
    <div class="b-destinations-box">
      <div class="b-section__header" style="margin-bottom: var(--space-5);">
        <div>
          <div class="b-section__eyebrow">Structured Taxonomy</div>
          <h1>Business Categories</h1>
        </div>
        <p style="font-size: var(--text-xs); color: var(--neutral-600); max-width: 44ch;">
          Browse verified local businesses across Bole Bulbula by service category.
        </p>
      </div>

      <!-- 2 in a row on Mobile (< 640px), 3 on Tablet, 4 on Desktop -->
      <div class="b-grid b-grid--categories">
        <?php foreach ($categories as $cat): ?>
          <div class="b-cat-bento" data-gsap="card">
            <div>
              <a href="/c/<?= View::e($cat['slug']) ?>" class="b-dest-card" style="min-height: 210px;">
                <img src="<?= View::e(View::categoryImage((string) $cat['slug'])) ?>" alt="<?= View::e($cat['name_en']) ?>" class="b-dest-card__img" loading="lazy">
                <div class="b-dest-card__shade"></div>

                <div class="b-dest-card__top">
                  <span class="b-dest-card__pill b-tabular">
                    <?= (int) ($cat['published_branch_count'] ?? 0) ?> Branches
                  </span>
                </div>

                <div class="b-dest-card__bottom">
                  <div class="b-dest-card__title"><?= View::e($cat['name_en']) ?></div>
                  <?php if (!empty($cat['name_am'])): ?>
                    <div class="b-dest-card__amharic b-lang-am" lang="am"><?= View::e($cat['name_am']) ?></div>
                  <?php endif; ?>
                  <span class="b-dest-card__desc"><?= View::e($cat['description_en'] ?? '') ?></span>
                </div>
              </a>

              <?php if (!empty($cat['subcategories'])): ?>
                <div style="margin-top: 0.55rem;">
                  <div class="b-subcat-pills">
                    <?php foreach ($cat['subcategories'] as $sub): ?>
                      <a href="/c/<?= View::e($sub['slug']) ?>" class="b-subcat-pill">
                        <?= View::e($sub['name_en']) ?> (<?= (int) ($sub['published_branch_count'] ?? 0) ?>)
                      </a>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>

            <div style="padding-top: 0.45rem; border-top: 1px solid var(--color-border-subtle); display: flex; flex-wrap: wrap; gap: 0.25rem;">
              <?php foreach (array_slice($areas, 0, 2) as $area): ?>
                <a href="/a/<?= View::e($area['slug']) ?>/<?= View::e($cat['slug']) ?>" class="b-subcat-pill">
                  In <?= View::e($area['name_en']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
