<?php
declare(strict_types=1);
use Bulbula\Core\View;

$branches = $branches ?? [];
?>
<div class="b-table-wrap">
  <table class="b-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Branch Name (EN / AM)</th>
        <th>Parent Business</th>
        <th>Primary Category (C-04)</th>
        <th>Area &amp; Sub-City (C-05)</th>
        <th>Hours Status (C-09)</th>
        <th>Publication Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($branches as $br): ?>
        <tr>
          <td class="b-tabular">#<?= (int) $br['branch_id'] ?></td>
          <td>
            <strong><?= View::e($br['branch_name_en']) ?></strong>
            <?php if (!empty($br['branch_name_am'])): ?>
              <div class="b-lang-am" lang="am" style="font-size: var(--text-xs); color: var(--orange-600);"><?= View::e($br['branch_name_am']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= View::e($br['business_name_en']) ?></td>
          <td><?= View::e($br['primary_category_name_en']) ?></td>
          <td><?= View::e($br['area_name_en']) ?> (<?= View::e($br['sub_city_en']) ?>)</td>
          <td><span class="b-badge b-badge--blue"><?= View::e($br['opening_hours_status']) ?></span></td>
          <td><span class="b-badge b-badge--verified"><?= View::e($br['publication_status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
