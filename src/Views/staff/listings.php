<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$branches   = $branches ?? [];
$categories = $categories ?? [];
$areas      = $areas ?? [];
$lifecycleTransitions = [
  'PUBLISHED'=>['TEMPORARILY_CLOSED'=>'Temporarily close','PERMANENTLY_CLOSED'=>'Permanently close','UNPUBLISHED'=>'Unpublish'],
  'TEMPORARILY_CLOSED'=>['PENDING_QC'=>'Reopen through QC','PERMANENTLY_CLOSED'=>'Permanently close','UNPUBLISHED'=>'Unpublish'],
  'PERMANENTLY_CLOSED'=>['PENDING_QC'=>'Reopen through QC'], 'UNPUBLISHED'=>['PENDING_QC'=>'Return to QC'],
  'DRAFT'=>['PENDING_QC'=>'Submit to QC','UNPUBLISHED'=>'Unpublish'], 'PENDING_QC'=>['DRAFT'=>'Return to draft','UNPUBLISHED'=>'Unpublish'],
];
?>
<section class="b-card b-listing-onboard">
  <header class="b-listing-onboard__header"><div><h2>Onboard a business and branch</h2><p>Capture permission, location, category, and contact details before verification.</p></div><span class="b-listing-onboard__step">Permission required</span></header>
  <form action="/staff/listings/create" method="POST" class="b-listing-form">
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
      <label class="b-label" for="lst-cat">Primary category</label>
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
      <label class="b-label" for="lst-area">Bole Bulbula area</label>
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
      <label class="b-label" for="lst-rep">Authorizing representative</label>
      <input id="lst-rep" type="text" name="rep_full_name" class="b-input" maxlength="160" placeholder="Full name" required>
    </div>
    <div>
      <label class="b-label" for="lst-rep-role">Representative role</label>
      <input id="lst-rep-role" type="text" name="rep_role_title" class="b-input" maxlength="120" placeholder="Owner or General Manager" required>
    </div>
    <div>
      <label class="b-label" for="lst-rep-phone">Representative phone</label>
      <input id="lst-rep-phone" type="tel" name="rep_phone" class="b-input" maxlength="40" autocomplete="off" placeholder="+251 9..." required>
    </div>
    <div>
      <label class="b-label" for="lst-rep-email">Representative email</label>
      <input id="lst-rep-email" type="email" name="rep_email" class="b-input" maxlength="190" autocomplete="off" placeholder="Required for verified email">
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
    <div>
      <label class="b-label" for="lst-evidence">Permission evidence reference</label>
      <input id="lst-evidence" type="text" name="permission_evidence_reference" class="b-input" maxlength="180" placeholder="Visit log, signed form, call or email reference" required>
    </div>
    <div class="b-listing-form__wide">
      <div style="flex: 1; min-width: 240px;">
        <label class="b-label" for="lst-sum">Short Description</label>
        <input id="lst-sum" type="text" name="brand_summary_en" class="b-input" placeholder="Short description of services and neighborhood specialty...">
      </div>
      <button type="submit" class="b-btn b-btn--primary b-btn--sm">Record Permission &amp; Queue for QC</button>
    </div>
  </form>
</section>

<div class="b-listing-summary"><strong><?= count($branches) ?> branches</strong><span>·</span><span>Operational directory portfolio and quick actions</span></div>
<div class="b-table-wrap">
  <table class="b-table b-listings-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Branch Name (EN / AM)</th>
        <th>Parent Business</th>
        <th>Primary category</th>
        <th>Area &amp; sub-city</th>
        <th>Hours status</th>
        <th>Publication Status</th>
        <th>Quick edit</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($branches as $br): ?>
        <tr>
          <td class="b-tabular">#<?= (int) $br['branch_id'] ?></td>
          <td class="b-listing-name">
            <strong><?= View::e($br['branch_name_en']) ?></strong>
            <?php if (!empty($br['branch_name_am'])): ?>
              <div class="b-lang-am" lang="am" style="font-size: var(--text-xs); color: var(--orange-600);"><?= View::e($br['branch_name_am']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= View::e($br['business_name_en']) ?></td>
          <td><?= View::e($br['primary_category_name_en']) ?></td>
          <td><?= View::e($br['area_name_en']) ?> (<?= View::e($br['sub_city_en']) ?>)</td>
          <td><span class="b-status-dot"><?= View::e(str_replace('_', ' ', $br['opening_hours_status'])) ?></span></td>
          <td><span class="b-badge b-badge--verified"><?= View::e($br['publication_status']) ?></span></td>
          <td class="b-listing-actions">
            <details class="b-listing-control"><summary>Correct listing details</summary><form action="/staff/listings/<?= (int) $br['branch_id'] ?>/update" method="POST" class="b-listing-actions__edit">
              <?= Csrf::field() ?>
              <label><span>Business name</span><input type="text" name="business_name_en" value="<?= View::e((string)$br['business_name_en']) ?>" class="b-input" maxlength="180" required></label>
              <label><span>Brand summary</span><input type="text" name="brand_summary_en" value="<?= View::e((string)($br['brand_summary_en']??'')) ?>" class="b-input" maxlength="500"></label>
              <label><span>Branch address</span><input type="text" name="address_line_en" value="<?= View::e((string)($br['address_line_en']??'')) ?>" class="b-input" maxlength="300" required></label>
              <label><span>Branch phone</span><input type="text" name="phone_primary" value="<?= View::e((string)($br['phone_primary']??'')) ?>" class="b-input" maxlength="40" placeholder="+251..."></label>
              <label><span>Hours data</span><select name="opening_hours_status" class="b-select">
                <option value="KNOWN" <?= ($br['opening_hours_status'] ?? '') === 'KNOWN' ? 'selected' : '' ?>>KNOWN</option>
                <option value="UNKNOWN" <?= ($br['opening_hours_status'] ?? '') === 'UNKNOWN' ? 'selected' : '' ?>>UNKNOWN</option>
                <option value="BY_APPOINTMENT" <?= ($br['opening_hours_status'] ?? '') === 'BY_APPOINTMENT' ? 'selected' : '' ?>>BY APPOINTMENT</option>
              </select></label>
              <p>Corrections to a live listing remove it from public discovery until quality control passes again.</p>
              <button type="submit" class="b-btn b-btn--outline b-btn--sm">Save correction</button>
            </form></details>
            <details class="b-listing-control b-listing-control--lifecycle"><summary>Change lifecycle status</summary><form action="/staff/listings/<?= (int)$br['branch_id'] ?>/lifecycle" method="POST" class="b-listing-actions__lifecycle">
              <?= Csrf::field() ?><label><span>Next status</span><select name="target_status" class="b-select" required><?php foreach($lifecycleTransitions[(string)$br['publication_status']]??[] as $status=>$label): ?><option value="<?= View::e($status) ?>"><?= View::e($label) ?></option><?php endforeach; ?></select></label>
              <label><span>Reason for change</span><textarea name="reason" class="b-input" minlength="10" maxlength="500" rows="2" required placeholder="State the verified operational reason"></textarea></label>
              <p>Closed and unpublished Branches leave public discovery immediately. Reopening always returns to QC.</p>
              <button type="submit" class="b-btn b-btn--outline b-btn--sm">Apply lifecycle change</button>
            </form></details>
            <form action="/staff/listings/<?= (int) $br['branch_id'] ?>/media" method="POST" enctype="multipart/form-data" class="b-listing-actions__media">
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
