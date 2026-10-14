<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$categories = $categories ?? [];
?>
<div class="b-card" style="margin-bottom: var(--space-5); padding: var(--space-4);">
  <h2 style="font-size: var(--text-base); margin-bottom: var(--space-3);">Add Category or Subcategory (C-23)</h2>
  <form action="/staff/taxonomy/save" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: var(--space-3); align-items: end;">
    <?= Csrf::field() ?>
    <div>
      <label class="b-label" for="tax-parent">Parent (Optional for Level 2)</label>
      <select id="tax-parent" name="parent_id" class="b-select">
        <option value="">None (Level 1 Category)</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= (int) $cat['id'] ?>"><?= View::e($cat['name_en']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="b-label" for="tax-en">English Name</label>
      <input id="tax-en" type="text" name="name_en" class="b-input" placeholder="e.g. Diagnostic Labs" required>
    </div>
    <div>
      <label class="b-label" for="tax-am">Amharic Name</label>
      <input id="tax-am" type="text" name="name_am" class="b-input" placeholder="e.g. የምርመራ ላቦራቶሪዎች">
    </div>
    <div>
      <label class="b-label" for="tax-slug">URL Slug</label>
      <input id="tax-slug" type="text" name="slug" class="b-input" placeholder="e.g. diagnostic-labs" required>
    </div>
    <div>
      <button type="submit" class="b-btn b-btn--primary b-btn--sm" style="width: 100%;">Save Category</button>
    </div>
  </form>
</div>

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
