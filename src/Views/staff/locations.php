<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$areas = $areas ?? [];
?>
<div class="b-card" style="margin-bottom: var(--space-5); padding: var(--space-4);">
  <h2 style="font-size: var(--text-base); margin-bottom: var(--space-3);">Add Controlled Local Area (C-24, Gate D-40)</h2>
  <form action="/staff/locations/save" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: var(--space-3); align-items: end;">
    <?= Csrf::field() ?>
    <div>
      <label class="b-label" for="area-en">Area Name (EN)</label>
      <input id="area-en" type="text" name="name_en" class="b-input" placeholder="e.g. Bulbula Mikael" required>
    </div>
    <div>
      <label class="b-label" for="area-am">Area Name (AM)</label>
      <input id="area-am" type="text" name="name_am" class="b-input" placeholder="e.g. ቡልቡላ ሚካኤል">
    </div>
    <div>
      <label class="b-label" for="area-slug">URL Slug</label>
      <input id="area-slug" type="text" name="slug" class="b-input" placeholder="e.g. bulbula-mikael" required>
    </div>
    <div>
      <label class="b-label" for="area-lat">Center Latitude</label>
      <input id="area-lat" type="text" name="center_latitude" class="b-input" placeholder="8.9510">
    </div>
    <div>
      <label class="b-label" for="area-lng">Center Longitude</label>
      <input id="area-lng" type="text" name="center_longitude" class="b-input" placeholder="38.7860">
    </div>
    <div>
      <button type="submit" class="b-btn b-btn--primary b-btn--sm" style="width: 100%;">Save Area</button>
    </div>
  </form>
</div>

<div class="b-table-wrap">
  <table class="b-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Slug</th>
        <th>Area Name (EN / AM)</th>
        <th>Sub-City</th>
        <th>Center Coordinates</th>
        <th>Launch Boundary (D-40)</th>
        <th>Published Branches</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($areas as $a): ?>
        <tr>
          <td class="b-tabular">#<?= (int) $a['id'] ?></td>
          <td style="font-family: var(--font-mono); font-size: var(--text-xs);"><?= View::e($a['slug']) ?></td>
          <td>
            <strong><?= View::e($a['name_en']) ?></strong>
            <div class="b-lang-am" lang="am" style="font-size: var(--text-xs); color: var(--orange-600);"><?= View::e($a['name_am'] ?? '') ?></div>
          </td>
          <td><?= View::e($a['sub_city_en']) ?></td>
          <td class="b-tabular" style="font-family: var(--font-mono); font-size: var(--text-xs);">
            <?= View::e((string) ($a['center_latitude'] ?? '')) ?>, <?= View::e((string) ($a['center_longitude'] ?? '')) ?>
          </td>
          <td><span class="b-badge b-badge--verified"><?= !empty($a['is_launch_area']) ? 'V1 Launch Area' : 'Future' ?></span></td>
          <td class="b-tabular"><?= (int) ($a['published_branch_count'] ?? 0) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
