<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$branches   = $branches ?? [];
$categories = $categories ?? [];
$areas      = $areas ?? [];
?>
<div class="b-card" style="margin-bottom: var(--space-5); padding: var(--space-4);">
  <h2 style="font-size: var(--text-base); margin-bottom: var(--space-3);">Onboard New Business, Permission &amp; Branch (C-19 Hard Gate)</h2>
  <form action="/staff/listings/create" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(175px, 1fr)); gap: var(--space-3); align-items: end;">
    <?= Csrf::field() ?>
    <div>
      <label class="b-label" for="lst-name-en">Business Name (EN)</label>
      <input id="lst-name-en" type="text" name="business_name_en" class="b-input" placeholder="e.g. Selam Diagnostic Clinic" required>
    </div>
    <div>
      <label class="b-label" for="lst-name-am">Business Name (AM)</label>
      <input id="lst-name-am" type="text" name="business_name_am" class="b-input" placeholder="e.g. ሰላም ክሊኒክ">
    </div>
    <div>
      <label class="b-label" for="lst-cat">Primary Category (C-04)</label>
      <select id="lst-cat" name="primary_category_id" class="b-select" required>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= (int) $cat['id'] ?>"><?= View::e($cat['name_en']) ?></option>
          <?php foreach ($cat['subcategories'] ?? [] as $sub): ?>
            <option value="<?= (int) $sub['id'] ?>">&nbsp;&nbsp;&mdash; <?= View::e($sub['name_en']) ?></option>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="b-label" for="lst-area">Bole Bulbula Area (C-05)</label>
      <select id="lst-area" name="area_id" class="b-select" required>
        <?php foreach ($areas as $a): ?>
          <option value="<?= (int) $a['id'] ?>"><?= View::e($a['name_en']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="b-label" for="lst-addr">Physical Address / Landmark</label>
      <input id="lst-addr" type="text" name="address_line_en" class="b-input" placeholder="93 Mazoria, next to..." required>
    </div>
    <div>
      <label class="b-label" for="lst-phone">Branch Phone</label>
      <input id="lst-phone" type="text" name="phone_primary" class="b-input" placeholder="+251 911 ...">
    </div>
    <div>
      <label class="b-label" for="lst-rep">Authorizing Representative (C-19)</label>
      <input id="lst-rep" type="text" name="rep_full_name" class="b-input" placeholder="Owner / General Manager" required>
    </div>
    <div>
      <label class="b-label" for="lst-method">Permission Method</label>
      <select id="lst-method" name="permission_method" class="b-select">
        <option value="RECORDED_VISIT">Recorded Field Visit</option>
        <option value="SIGNED_FORM">Signed Permission Form</option>
        <option value="VERIFIED_PHONE">Verified Phone Confirmation</option>
        <option value="VERIFIED_EMAIL">Verified Email Confirmation</option>
      </select>
    </div>
    <div style="grid-column: 1 / -1; display: flex; gap: var(--space-3); align-items: end; flex-wrap: wrap;">
      <div style="flex: 1; min-width: 240px;">
        <label class="b-label" for="lst-sum">Short Description</label>
        <input id="lst-sum" type="text" name="brand_summary_en" class="b-input" placeholder="Short description of services and neighborhood specialty...">
      </div>
      <button type="submit" class="b-btn b-btn--primary b-btn--sm">Record Permission &amp; Queue for QC</button>
    </div>
  </form>
</div>

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
        <th>Quick Edit (C-20)</th>
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
          <td>
            <form action="/staff/listings/<?= (int) $br['branch_id'] ?>/update" method="POST" style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;">
              <?= Csrf::field() ?>
              <input type="hidden" name="address_line_en" value="<?= View::e((string) ($br['address_line_en'] ?? '')) ?>">
              <input type="text" name="phone_primary" value="<?= View::e((string) ($br['phone_primary'] ?? '')) ?>" class="b-input" style="width: 125px; min-height: 30px; padding: 0.2rem 0.45rem; font-size: 0.72rem;" placeholder="+251...">
              <select name="opening_hours_status" class="b-select" style="width: 118px; min-height: 30px; padding: 0.2rem 0.4rem; font-size: 0.72rem;">
                <option value="KNOWN" <?= ($br['opening_hours_status'] ?? '') === 'KNOWN' ? 'selected' : '' ?>>KNOWN</option>
                <option value="UNKNOWN" <?= ($br['opening_hours_status'] ?? '') === 'UNKNOWN' ? 'selected' : '' ?>>UNKNOWN</option>
                <option value="BY_APPOINTMENT" <?= ($br['opening_hours_status'] ?? '') === 'BY_APPOINTMENT' ? 'selected' : '' ?>>BY_APPOINTMENT</option>
              </select>
              <button type="submit" class="b-btn b-btn--outline b-btn--sm" style="padding: 0.2rem 0.55rem; font-size: 0.7rem;">Save</button>
            </form>
            <form action="/staff/listings/<?= (int) $br['branch_id'] ?>/media" method="POST" enctype="multipart/form-data" style="display:flex;align-items:center;gap:.35rem;flex-wrap:wrap;margin-top:.45rem;">
              <?= Csrf::field() ?>
              <input type="hidden" name="MAX_FILE_SIZE" value="8388608">
              <input type="file" name="branch_image" accept="image/jpeg,image/png,image/webp" required class="b-input" style="width:170px;min-height:30px;padding:.2rem;font-size:.68rem;">
              <input type="text" name="alt_text_en" value="<?= View::e((string) $br['branch_name_en']) ?>" required maxlength="180" class="b-input" style="width:125px;min-height:30px;padding:.2rem .4rem;font-size:.68rem;" aria-label="Image alternative text">
              <button type="submit" class="b-btn b-btn--outline b-btn--sm" style="padding:.2rem .5rem;font-size:.7rem;">Upload Photo</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
