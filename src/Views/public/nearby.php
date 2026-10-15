<?php
declare(strict_types=1);
use Bulbula\Core\View;

$p          = $payload ?? [];
$hasCoords  = !empty($hasCoords) || !empty($p['has_coordinates']);
$lat        = $lat ?? ($p['latitude'] ?? 8.9538);
$lng        = $lng ?? ($p['longitude'] ?? 38.7892);
$radiusM    = (int) ($radiusM ?? ($p['radius_meters'] ?? 3500));
$branches   = !empty($branches)
    ? $branches
    : (!empty($p['nearby_branches']) ? $p['nearby_branches'] : ($p['default_branches'] ?? []));
$areas      = !empty($areas) ? $areas : ($p['fallback_areas'] ?? []);
$radii      = [1000 => '1 km', 2000 => '2 km', 3500 => '3.5 km', 5000 => '5 km'];
?>
<section class="b-section" style="padding: var(--space-5) 0 var(--space-4);">
  <div class="b-container">
    <!-- Rounded Photographic Nearby & Corridor Banner -->
    <div style="position: relative; border-radius: 22px; overflow: hidden; padding: clamp(1.5rem, 3.5vw, 2.75rem); background: #141210; color: #FFFFFF; box-shadow: var(--shadow-lg);">
      <img src="<?= View::asset('img/cards/hero-bole-bulbula-bg.jpg') ?>" alt="Nearby in Bole Bulbula" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0.48;">
      <div style="position: absolute; inset: 0; background: linear-gradient(95deg, rgba(16,13,10,0.92) 0%, rgba(20,16,12,0.78) 55%, rgba(20,16,12,0.4) 100%);"></div>

      <div style="position: relative; z-index: 2; max-width: 720px;">
        <div class="b-section__eyebrow" style="color: #F28538;">Proximity Discovery</div>
        <h1 style="color: #FFFFFF; font-size: clamp(1.45rem, 3vw, 2.35rem);">Nearby in Bole Bulbula</h1>
        <p style="margin-top: var(--space-2); color: rgba(255,255,255,0.84); font-size: var(--text-sm);">
          Find verified branches sorted by distance from your location or any Bole Bulbula landmark — no mandatory GPS required.
        </p>

        <!-- Landmark & GPS Trigger Row -->
        <div style="display: flex; flex-wrap: wrap; gap: 0.55rem; align-items: center; margin-top: var(--space-4);">
          <button type="button" class="b-btn b-btn--primary b-btn--sm" data-locate-me-btn>
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

        <!-- Radius Selector Pills -->
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.45rem; margin-top: var(--space-3);">
          <span style="font-size: var(--text-xs); color: rgba(255,255,255,0.72); font-weight: 600;">Search Radius:</span>
          <?php foreach ($radii as $rMeters => $rLabel): ?>
            <a href="/nearby?lat=<?= View::e((string) $lat) ?>&amp;lng=<?= View::e((string) $lng) ?>&amp;radius=<?= $rMeters ?>"
               class="b-filter-pill <?= $radiusM === $rMeters ? 'is-active' : '' ?>"
               <?= $radiusM === $rMeters ? 'aria-current="true"' : '' ?>
               style="padding: 0.28rem 0.75rem; font-size: 0.75rem;">
              <?= View::e($rLabel) ?>
            </a>
          <?php endforeach; ?>
        </div>

        <p data-nearby-status style="font-size: var(--text-xs); color: #F28538; margin-top: var(--space-2);"></p>
      </div>
    </div>
  </div>
</section>

<section class="b-section" style="padding-top: var(--space-3);">
  <div class="b-container">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4);">
      <span class="b-tabular" style="font-size: var(--text-sm); color: var(--neutral-600);">
        Showing <strong style="color: var(--neutral-900);"><?= count($branches) ?></strong> verified <?= count($branches) === 1 ? 'branch' : 'branches' ?> within <?= number_format($radiusM / 1000, 1) ?> km
      </span>
      <a href="/areas" style="font-size: var(--text-xs); font-weight: 600; color: var(--orange-500);">Browse by Corridor &rsaquo;</a>
    </div>

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
