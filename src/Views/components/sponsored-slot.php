<?php
declare(strict_types=1);
use Bulbula\Core\View;

/**
 * Clean Light-Orange Sponsored Spotlight Zone (C-16) with Blended Visual Cards.
 */
$items = $sponsoredItems ?? [];
$slotTitle = $slotTitle ?? 'Featured Partner';
if (empty($items)) {
    return;
}
?>
<section class="b-sponsored-zone" aria-label="Sponsored placement">
  <div class="b-sponsored-zone__header">
    <div style="display: flex; align-items: center; gap: 0.5rem;">
      <span class="b-badge b-badge--sponsored">Sponsored</span>
      <span style="font-size: var(--text-sm); font-weight: 600; color: var(--neutral-900);"><?= View::e($slotTitle) ?></span>
    </div>
    <a href="/policies/sponsorship" class="b-sponsored-zone__disclaimer">
      Clearly separated from organic results &rarr;
    </a>
  </div>

  <div class="b-grid b-grid--branches">
    <?php foreach ($items as $sp): ?>
      <?php
        $url = '/b/' . ($sp['business_slug'] ?? '') . '/' . ($sp['branch_slug'] ?? '');
        $actionEndpoint = $url . '/act';
        $coverImg = View::branchImage($sp);
      ?>
      <article class="b-card" style="border-color: var(--orange-200); background: #FFFFFF;">
        <div>
          <a href="<?= View::e($url) ?>"
             class="b-card__media"
             style="display: block;"
             data-branch-action="website"
             data-campaign-id="<?= (int) ($sp['campaign_id'] ?? 0) ?>"
             data-action-endpoint="<?= View::e($actionEndpoint) ?>">
            <img src="<?= View::e($coverImg) ?>" alt="<?= View::e($sp['branch_name_en'] ?? $sp['business_name_en'] ?? '') ?>" loading="lazy">
            <div class="b-card__media-badges">
              <?= View::component('trust-badge', ['isVerified' => !empty($sp['is_verified'])]) ?>
              <?php if (!empty($sp['open_now'])): ?>
                <span class="b-status-pill b-status-pill--<?= View::e($sp['open_now']['css_modifier']) ?>">
                  <span class="b-status-pill__dot"></span>
                  <span><?= View::e($sp['open_now']['badge_label']) ?></span>
                </span>
              <?php endif; ?>
            </div>
          </a>

          <div class="b-card__body">
            <h3 class="b-card__title">
              <a href="<?= View::e($url) ?>"
                 data-branch-action="website"
                 data-campaign-id="<?= (int) ($sp['campaign_id'] ?? 0) ?>"
                 data-action-endpoint="<?= View::e($actionEndpoint) ?>">
                <?= View::e($sp['branch_name_en'] ?? $sp['business_name_en'] ?? '') ?>
              </a>
            </h3>
            <p class="b-card__summary"><?= View::e($sp['brand_summary_en'] ?? '') ?></p>
          </div>
        </div>

        <div class="b-card__footer">
          <span style="font-size: var(--text-xs); color: var(--neutral-600);">
            <?= View::e($sp['area_name_en'] ?? 'Bole Bulbula') ?>
          </span>
          <a href="<?= View::e($url) ?>"
             class="b-btn b-btn--primary b-btn--sm"
             data-branch-action="website"
             data-campaign-id="<?= (int) ($sp['campaign_id'] ?? 0) ?>"
             data-action-endpoint="<?= View::e($actionEndpoint) ?>">
            <span>Open &rarr;</span>
          </a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
