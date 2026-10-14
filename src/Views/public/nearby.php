<?php
declare(strict_types=1);
use Bulbula\Core\View;

$hasCoords = !empty($hasCoords);
$lat       = $lat ?? null;
$lng       = $lng ?? null;
$radiusM   = (int) ($radiusM ?? 3500);
$branches  = $branches ?? [];
$areas     = $areas ?? [];
?>
<section class="b-section b-section--tint" style="padding: var(--space-8) 0;">
  <div class="b-container">
    <div class="b-section__eyebrow">Proximity Discovery</div>
    <h1>Nearby in Bole Bulbula</h1>
    <p style="margin-top: var(--space-2);">
      Find verified businesses sorted by distance from your location or any Bole Bulbula landmark.
    </p>

    <div style="display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: center; margin-top: var(--space-5);">
      <button type="button" class="b-btn b-btn--primary" data-locate-me-btn>
        <span>Use My Current Location</span>
      </button>

      <?php foreach ($areas as $a): ?>
        <?php if (!empty($a['center_lat']) && !empty($a['center_lng'])): ?>
          <a href="/nearby?lat=<?= View::e((string) $a['center_lat']) ?>&amp;lng=<?= View::e((string) $a['center_lng']) ?>&amp;radius=<?= $radiusM ?>"
             class="b-chip">
            Near <?= View::e($a['name_en']) ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <p data-nearby-status style="font-size: var(--text-xs); color: var(--neutral-600); margin-top: var(--space-2);"></p>
  </div>
</section>

<section class="b-section">
  <div class="b-container">
    <?php if (empty($branches)): ?>
      <div class="b-card" style="text-align: center; padding: var(--space-10);">
        <h3>No branches found within <?= number_format($radiusM / 1000, 1) ?> km</h3>
        <p style="margin: var(--space-2) auto var(--space-4);">Try increasing the search radius or selecting a Bole Bulbula corridor above.</p>
        <a href="/nearby?lat=8.9538&amp;lng=38.7892&amp;radius=5000" class="b-btn b-btn--primary">Expand to 5 km</a>
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
