<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$categories = $categories ?? [];
$subcategoryCount = 0;
$publishedBranches = 0;
foreach ($categories as $category) {
    $publishedBranches += (int) ($category['published_branch_count'] ?? 0);
    foreach ($category['subcategories'] ?? [] as $subcategory) {
        $subcategoryCount++;
        $publishedBranches += (int) ($subcategory['published_branch_count'] ?? 0);
    }
}
?>
<section class="b-taxonomy-summary" aria-label="Taxonomy summary">
  <article class="b-taxonomy-stat"><span class="b-taxonomy-stat__label">Primary categories</span><strong class="b-taxonomy-stat__value"><?= count($categories) ?></strong><span class="b-taxonomy-stat__meta">Level-one discovery groups</span></article>
  <article class="b-taxonomy-stat"><span class="b-taxonomy-stat__label">Subcategories</span><strong class="b-taxonomy-stat__value"><?= $subcategoryCount ?></strong><span class="b-taxonomy-stat__meta">Level-two directory classifications</span></article>
  <article class="b-taxonomy-stat"><span class="b-taxonomy-stat__label">Published assignments</span><strong class="b-taxonomy-stat__value"><?= $publishedBranches ?></strong><span class="b-taxonomy-stat__meta">Branch-category relationships</span></article>
</section>

<section class="b-card b-taxonomy-create">
  <header class="b-taxonomy-create__head"><h2>Add a category or subcategory</h2><p>Maintain the controlled two-level directory vocabulary used across the public directory.</p></header>
  <form action="/staff/taxonomy/save" method="POST" class="b-taxonomy-form">
    <?= Csrf::field() ?>
    <div><label class="b-label" for="tax-parent">Parent category</label><select id="tax-parent" name="parent_id" class="b-select"><option value="">None — create level one</option><?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>"><?= View::e($cat['name_en']) ?></option><?php endforeach; ?></select></div>
    <div><label class="b-label" for="tax-en">English name</label><input id="tax-en" type="text" name="name_en" class="b-input" placeholder="e.g. Diagnostic Labs" required></div>
    <div><label class="b-label" for="tax-am">Amharic name</label><input id="tax-am" type="text" name="name_am" class="b-input" placeholder="e.g. የምርመራ ላቦራቶሪዎች" lang="am"></div>
    <div><label class="b-label" for="tax-slug">URL slug</label><input id="tax-slug" type="text" name="slug" class="b-input" placeholder="e.g. diagnostic-labs" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required></div>
    <div><button type="submit" class="b-btn b-btn--primary b-btn--sm">Save classification</button></div>
  </form>
</section>

<section class="b-card b-taxonomy-register">
  <header class="b-taxonomy-register__head"><h2>Directory classification register</h2><span>Two-level hierarchy · English and Amharic labels</span></header>
  <?php if ($categories === []): ?>
    <div class="b-taxonomy-empty"><strong>No categories configured</strong>Create the first level-one category to begin the directory taxonomy.</div>
  <?php else: ?>
    <div class="b-table-wrap" style="border:0;border-radius:0;box-shadow:none"><table class="b-table b-taxonomy-table"><thead><tr><th>Hierarchy</th><th>Classification</th><th>URL slug</th><th>Amharic label</th><th>Published branches</th></tr></thead><tbody>
      <?php foreach ($categories as $cat): ?>
        <tr><td><span class="b-taxonomy-level">Level 1</span></td><td class="b-taxonomy-name"><strong><?= View::e($cat['name_en']) ?></strong><span>Primary discovery category</span></td><td><span class="b-taxonomy-slug"><?= View::e($cat['slug']) ?></span></td><td class="b-lang-am" lang="am"><?= View::e($cat['name_am'] ?? '—') ?></td><td><span class="b-taxonomy-count"><?= (int) ($cat['published_branch_count'] ?? 0) ?></span></td></tr>
        <?php foreach ($cat['subcategories'] ?? [] as $sub): ?>
          <tr><td><span class="b-taxonomy-level b-taxonomy-level--child">Level 2</span></td><td class="b-taxonomy-name"><strong><?= View::e($sub['name_en']) ?></strong><span>Under <?= View::e($cat['name_en']) ?></span></td><td><span class="b-taxonomy-slug"><?= View::e($sub['slug']) ?></span></td><td class="b-lang-am" lang="am"><?= View::e($sub['name_am'] ?? '—') ?></td><td><span class="b-taxonomy-count"><?= (int) ($sub['published_branch_count'] ?? 0) ?></span></td></tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
