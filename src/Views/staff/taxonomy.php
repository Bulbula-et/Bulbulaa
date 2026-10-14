<?php
declare(strict_types=1);
use Bulbula\Core\View;

$categories = $categories ?? [];
?>
<div class="b-table-wrap">
  <table class="b-table">
    <thead>
      <tr>
        <th>Level</th>
        <th>Slug</th>
        <th>English Label</th>
        <th>Amharic Label (C-04)</th>
        <th>Published Branches</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($categories as $cat): ?>
        <tr>
          <td><span class="b-badge b-badge--blue">Level 1 Category</span></td>
          <td style="font-family: var(--font-mono); font-size: var(--text-xs);"><?= View::e($cat['slug']) ?></td>
          <td><strong><?= View::e($cat['name_en']) ?></strong></td>
          <td class="b-lang-am" lang="am"><?= View::e($cat['name_am'] ?? '—') ?></td>
          <td class="b-tabular"><?= (int) ($cat['published_branch_count'] ?? 0) ?></td>
        </tr>
        <?php foreach ($cat['subcategories'] ?? [] as $sub): ?>
          <tr>
            <td style="padding-left: 2rem;"><span class="b-badge b-badge--orange">&vdash; Level 2 Subcategory</span></td>
            <td style="font-family: var(--font-mono); font-size: var(--text-xs);"><?= View::e($sub['slug']) ?></td>
            <td><?= View::e($sub['name_en']) ?></td>
            <td class="b-lang-am" lang="am"><?= View::e($sub['name_am'] ?? '—') ?></td>
            <td class="b-tabular"><?= (int) ($sub['published_branch_count'] ?? 0) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
