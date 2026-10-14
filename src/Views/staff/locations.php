<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$areas = $areas ?? [];
$launchAreas = 0;
$publishedBranches = 0;
foreach ($areas as $area) {
    if (!empty($area['is_launch_area'])) $launchAreas++;
    $publishedBranches += (int) ($area['published_branch_count'] ?? 0);
}
?>
<section class="b-location-boundary">
  <div class="b-location-boundary__main"><h2>Controlled launch geography</h2><p>Directory coverage is constrained to approved local areas so verification, discovery, and operational quality can scale without weakening geographic accuracy.</p><span class="b-location-boundary__gate">Gate D-40 · Bole launch boundary</span></div>
  <article class="b-location-stat"><span class="b-location-stat__label">Controlled areas</span><strong class="b-location-stat__value"><?= count($areas) ?></strong><span class="b-location-stat__meta">Configured local zones</span></article>
  <article class="b-location-stat"><span class="b-location-stat__label">V1 launch areas</span><strong class="b-location-stat__value"><?= $launchAreas ?></strong><span class="b-location-stat__meta">Enabled for publication</span></article>
  <article class="b-location-stat"><span class="b-location-stat__label">Published branches</span><strong class="b-location-stat__value"><?= $publishedBranches ?></strong><span class="b-location-stat__meta">Assigned across areas</span></article>
</section>

<section class="b-card b-location-create">
  <header class="b-location-create__head"><h2>Add a controlled local area</h2><p>Register the bilingual name, stable URL, and geographic center defined by C-24.</p></header>
  <form action="/staff/locations/save" method="POST" class="b-location-form">
    <?= Csrf::field() ?>
    <div><label class="b-label" for="area-en">English name</label><input id="area-en" type="text" name="name_en" class="b-input" placeholder="e.g. Bulbula Mikael" required></div>
    <div><label class="b-label" for="area-am">Amharic name</label><input id="area-am" type="text" name="name_am" class="b-input" placeholder="e.g. ቡልቡላ ሚካኤል" lang="am"></div>
    <div><label class="b-label" for="area-slug">URL slug</label><input id="area-slug" type="text" name="slug" class="b-input" placeholder="e.g. bulbula-mikael" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required></div>
    <div><label class="b-label" for="area-lat">Center latitude</label><input id="area-lat" type="number" step="0.0000001" min="-90" max="90" name="center_latitude" class="b-input" placeholder="8.9510"></div>
    <div><label class="b-label" for="area-lng">Center longitude</label><input id="area-lng" type="number" step="0.0000001" min="-180" max="180" name="center_longitude" class="b-input" placeholder="38.7860"></div>
    <div><button type="submit" class="b-btn b-btn--primary b-btn--sm">Save area</button></div>
  </form>
</section>

<section class="b-card b-location-register">
  <header class="b-location-register__head"><h2>Controlled area register</h2><span>Location vocabulary, map centers, and launch eligibility</span></header>
  <?php if ($areas === []): ?>
    <div class="b-location-empty"><strong>No controlled areas configured</strong>Add the first approved area to establish the directory launch boundary.</div>
  <?php else: ?>
    <div class="b-table-wrap" style="border:0;border-radius:0;box-shadow:none"><table class="b-table b-location-table"><thead><tr><th>Area</th><th>URL slug</th><th>Sub-city</th><th>Map center</th><th>Launch status</th><th>Published branches</th></tr></thead><tbody>
      <?php foreach ($areas as $area): ?>
        <tr>
          <td class="b-location-area"><strong><?= View::e($area['name_en']) ?></strong><span class="b-lang-am" lang="am"><?= View::e($area['name_am'] ?? '—') ?></span></td>
          <td><span class="b-location-slug"><?= View::e($area['slug']) ?></span></td>
          <td><?= View::e($area['sub_city_en']) ?></td>
          <td><span class="b-location-coordinates"><span><?= View::e((string) ($area['center_latitude'] ?? 'Not set')) ?></span><span><?= View::e((string) ($area['center_longitude'] ?? 'Not set')) ?></span></span></td>
          <td><span class="b-badge <?= !empty($area['is_launch_area']) ? 'b-badge--verified' : 'b-badge--blue' ?>"><?= !empty($area['is_launch_area']) ? 'V1 launch area' : 'Future area' ?></span></td>
          <td><span class="b-location-usage"><?= (int) ($area['published_branch_count'] ?? 0) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
