<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$savedItems = $savedItems ?? [];
?>
<div class="b-container" style="padding-top: var(--space-6);">
  <div class="b-section__header">
    <div>
      <div class="b-section__eyebrow">Bookmarked Directory</div>
      <h1>Saved Places (<?= count($savedItems) ?>)</h1>
    </div>
    <a href="/account" class="b-btn b-btn--outline b-btn--sm">&larr; Account</a>
  </div>

  <?php if (!empty($savedItems)): ?>
    <div class="b-grid b-grid--branches">
      <?php foreach ($savedItems as $item): ?>
        <article class="b-card">
          <div>
            <span class="b-badge b-badge--orange"><?= View::e($item['primary_category_name_en'] ?? 'Business') ?></span>
            <h3 class="b-card__title" style="margin-top: var(--space-2);">
              <?php if (($item['publication_status'] ?? '') === 'PUBLISHED'): ?>
                <a href="/b/<?= View::e($item['business_slug']) ?>/<?= View::e($item['branch_slug']) ?>">
                  <?= View::e($item['business_name_en']) ?>
                </a>
              <?php else: ?>
                <?= View::e($item['business_name_en']) ?> <span class="b-badge b-badge--orange">Unavailable</span>
              <?php endif; ?>
            </h3>
            <p style="font-size: var(--text-xs); color: var(--neutral-600); margin-top: 0.35rem;">
              <?= View::e($item['area_name_en'] ?? 'Bole Bulbula') ?> &middot; <?= View::e($item['address_line_en'] ?? '') ?>
            </p>
          </div>

          <div class="b-card__footer">
            <form method="POST" action="/account/saved/toggle">
              <?= Csrf::field() ?>
              <input type="hidden" name="business_id" value="<?= (int) $item['business_id'] ?>">
              <input type="hidden" name="return_to" value="/account/saved">
              <button type="submit" class="b-btn b-btn--outline b-btn--sm">Remove</button>
            </form>
            <?php if (($item['publication_status'] ?? '') === 'PUBLISHED'): ?>
              <a href="/b/<?= View::e($item['business_slug']) ?>/<?= View::e($item['branch_slug']) ?>" class="b-btn b-btn--primary b-btn--sm">Open &rarr;</a>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="b-empty-state">
      <h3>No saved places yet</h3>
      <p style="font-size: var(--text-sm); color: var(--neutral-600); margin-top: 0.35rem;">
        Tap &ldquo;Save Branch&rdquo; on any business profile to bookmark it here.
      </p>
      <div style="margin-top: var(--space-4);">
        <a href="/" class="b-btn b-btn--primary">Explore Bole Bulbula</a>
      </div>
    </div>
  <?php endif; ?>
</div>
