<?php
declare(strict_types=1);
use Bulbula\Core\View;

/**
 * Cloned 2-in-a-Row Visual Business Card (Agriplant "Featured Products" & Image 2 "Featured Events").
 * - White outer card frame with rounded top image
 * - Top-left Verified badge + Top-right Bookmark icon button on image
 * - Below image: Open status pill + Star rating, Bold Name, Unclamped Full Short Description
 * - Bottom row: Location pin & Area on left + Square Orange Action button on right
 */
$b = $branch ?? [];
$openNow = $b['open_now'] ?? ['state' => 'UNKNOWN', 'badge_label' => 'Hours Not Recorded', 'css_modifier' => 'unknown'];
$isVerified = !empty($b['is_verified']);
$url = $b['canonical_url'] ?? ('/b/' . ($b['business_slug'] ?? '') . '/' . ($b['branch_slug'] ?? ''));
$actionEndpoint = $url . '/act';
$coverImg = View::branchImage($b);
?>
<article class="b-card" data-gsap="card">
  <div>
    <!-- Framed Rounded Image with Top-Left Verified & Top-Right Bookmark Button -->
    <div class="b-card__media">
      <a href="<?= View::e($url) ?>" style="display: block; width: 100%; height: 100%;" aria-label="<?= View::e($b['branch_name_en'] ?? $b['business_name_en'] ?? '') ?>">
        <img src="<?= View::e($coverImg) ?>" alt="<?= View::e($b['branch_name_en'] ?? $b['business_name_en'] ?? '') ?>" loading="lazy">
      </a>
      <div class="b-card__media-badges">
        <div>
          <?= View::component('trust-badge', [
              'isVerified' => $isVerified,
              'verifiedAt' => $b['last_verified_at'] ?? null,
          ]) ?>
        </div>

        <a href="<?= View::e($url) ?>" class="b-card__bookmark" title="Save or View Branch" aria-label="Bookmark <?= View::e($b['branch_name_en'] ?? '') ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
          </svg>
        </a>
      </div>
    </div>

    <!-- Card Body: Status + Rating, Name & Full Unclamped Short Description -->
    <div class="b-card__body" style="margin-top: 0.45rem;">
      <div class="b-card__meta-top">
        <span class="b-status-pill b-status-pill--<?= View::e($openNow['css_modifier']) ?>">
          <span class="b-status-pill__dot"></span>
          <span><?= View::e($openNow['badge_label']) ?></span>
        </span>

        <?php if (!empty($b['published_review_count']) && (int) $b['published_review_count'] > 0): ?>
          <span class="b-rating b-tabular">
            <span class="b-rating__star">&#9733;</span>
            <span><?= number_format((float) $b['published_rating_avg'], 1) ?></span>
          </span>
        <?php elseif (!empty($b['distance_label'])): ?>
          <span class="b-badge b-badge--orange b-tabular"><?= View::e($b['distance_label']) ?></span>
        <?php endif; ?>
      </div>

      <h3 class="b-card__title">
        <a href="<?= View::e($url) ?>"><?= View::e($b['branch_name_en'] ?? $b['business_name_en'] ?? '') ?></a>
      </h3>
      <?php if (!empty($b['branch_name_am']) || !empty($b['business_name_am'])): ?>
        <div class="b-card__amharic b-lang-am" lang="am">
          <?= View::e($b['branch_name_am'] ?? $b['business_name_am']) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($b['brand_summary_en'])): ?>
        <p class="b-card__summary"><?= View::e($b['brand_summary_en']) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Bottom Row: Location Left + Square Action Button Right (Cloned from Image 1) -->
  <div class="b-card__footer">
    <span class="b-card__loc">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--orange-500)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
        <path d="M12 21s-7-5.686-7-11a7 7 0 1 1 14 0c0 5.314-7 11-7 11z"/>
        <circle cx="12" cy="10" r="2.5"/>
      </svg>
      <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
        <?= View::e($b['area_name_en'] ?? 'Bole Bulbula') ?>
      </span>
    </span>

    <?php if (!empty($b['phone_primary'])): ?>
      <a href="tel:<?= View::e($b['phone_primary']) ?>"
         class="b-card__sq-btn"
         title="Call <?= View::e($b['phone_primary']) ?>"
         aria-label="Call <?= View::e($b['branch_name_en'] ?? '') ?>"
         data-branch-action="call"
         data-action-endpoint="<?= View::e($actionEndpoint) ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
      </a>
    <?php else: ?>
      <a href="<?= View::e($url) ?>"
         class="b-card__sq-btn"
         title="Open Details"
         aria-label="Open <?= View::e($b['branch_name_en'] ?? '') ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 12h14M12 5l7 7-7 7"/>
        </svg>
      </a>
    <?php endif; ?>
  </div>
</article>
