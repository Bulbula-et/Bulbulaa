<?php
declare(strict_types=1);
use Bulbula\Core\View;

$areas = $areas ?? [];
?>
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
            <?= View::e($a['center_latitude'] ?? '') ?>, <?= View::e($a['center_longitude'] ?? '') ?>
          </td>
          <td><span class="b-badge b-badge--verified"><?= !empty($a['is_launch_area']) ? 'V1 Launch Area' : 'Future' ?></span></td>
          <td class="b-tabular"><?= (int) ($a['published_branch_count'] ?? 0) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
