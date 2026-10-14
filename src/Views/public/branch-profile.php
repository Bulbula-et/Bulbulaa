<?php
declare(strict_types=1);
use Bulbula\Core\View;
use Bulbula\Core\SecurityHeaders;

/**
 * C-08..C-14 Branch Profile Details View — Cloned from Image 1 Middle-Left "Details" Screen
 */
$b              = $branch ?? [];
$openNow        = $b['open_now'] ?? ['state' => 'UNKNOWN', 'badge_label' => 'Hours Not Recorded', 'css_modifier' => 'unknown', 'reason' => ''];
$weeklySchedule = $b['weekly_schedule'] ?? [];
$reviews        = $reviews ?? [];
$isSaved        = !empty($isSaved);
$canonicalPath  = '/b/' . ($b['business_slug'] ?? '') . '/' . ($b['branch_slug'] ?? '');
$actionEndpoint = $canonicalPath . '/act';
$flashSuccess   = $flashSuccess ?? null;
$flashError     = $flashError ?? null;
$dayNames       = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
$coverImg       = View::branchImage($b);

$jsonLd = [
    '@context'    => 'https://schema.org',
    '@type'       => 'LocalBusiness',
    'name'        => (string) ($b['branch_name_en'] ?? $b['business_name_en'] ?? 'Bole Bulbula Business'),
    'description' => (string) ($b['brand_summary_en'] ?? ''),
    'url'         => 'https://bulbula.et' . $canonicalPath,
    'address'     => [
        '@type'           => 'PostalAddress',
        'streetAddress'   => (string) ($b['address_line_en'] ?? ''),
        'addressLocality' => (string) ($b['area_name_en'] ?? 'Bole Bulbula'),
        'addressRegion'   => 'Addis Ababa',
        'addressCountry'  => 'ET',
    ],
];
if (!empty($b['phone_primary'])) {
    $jsonLd['telephone'] = (string) $b['phone_primary'];
}
if (!empty($b['latitude']) && !empty($b['longitude'])) {
    $jsonLd['geo'] = [
        '@type'     => 'GeoCoordinates',
        'latitude'  => (float) $b['latitude'],
        'longitude' => (float) $b['longitude'],
    ];
}
if ((int) ($b['published_review_count'] ?? 0) > 0 && $b['published_rating_avg'] !== null) {
    $jsonLd['aggregateRating'] = [
        '@type'       => 'AggregateRating',
        'ratingValue' => round((float) $b['published_rating_avg'], 2),
        'reviewCount' => (int) $b['published_review_count'],
    ];
}
?>
<script nonce="<?= SecurityHeaders::nonce() ?>" type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>

<section class="b-section" style="padding-top: var(--space-5);">
  <div class="b-container">
    <!-- Top App Details Bar: < Back | Details | Bookmark (Cloned from Image 1 "Details" Screen) -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4);">
      <a href="/search" class="b-btn b-btn--outline b-btn--sm" style="border-radius: 12px;">
        <span>&larr; Back</span>
      </a>
      <span style="font-size: var(--text-sm); font-weight: 700; color: var(--neutral-900);">
        <?= View::e($b['primary_category_name_en'] ?? 'Branch Details') ?>
      </span>
      <div>
        <?php if (!empty($customer)): ?>
          <form action="/account/saved/toggle" method="POST" style="display: inline;">
            <?= View::csrfField() ?>
            <input type="hidden" name="branch_id" value="<?= (int) ($b['id'] ?? 0) ?>">
            <input type="hidden" name="return_to" value="<?= View::e($canonicalPath) ?>">
            <button type="submit" class="b-card__bookmark" style="width: 38px; height: 38px; border-radius: 12px; cursor: pointer;" title="Save Branch">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= $isSaved ? 'var(--orange-500)' : 'none' ?>" stroke="currentColor" stroke-width="2.2">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
              </svg>
            </button>
          </form>
        <?php else: ?>
          <a href="/auth/login" class="b-card__bookmark" style="width: 38px; height: 38px; border-radius: 12px;" title="Sign in to Save">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
            </svg>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="b-alert b-alert--success"><?= View::e((string) $flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="b-alert b-alert--error"><?= View::e((string) $flashError) ?></div>
    <?php endif; ?>

    <!-- Cloned Details Hero Card with Large Rounded Visual & Interactive 3-Photo Carousel Dots -->
    <?php
      $catImg  = View::categoryImage((string) ($b['primary_category_slug'] ?? ''));
      $areaImg = View::areaImage((string) ($b['area_slug'] ?? ''));
      $gallerySlides = [
          ['src' => $coverImg, 'label' => ($b['branch_name_en'] ?? 'Branch') . ' — Main View'],
          ['src' => $catImg,   'label' => ($b['primary_category_name_en'] ?? 'Category') . ' — Interior'],
          ['src' => $areaImg,  'label' => ($b['area_name_en'] ?? 'Bole Bulbula') . ' — Corridor'],
      ];
    ?>
    <div class="b-profile-header">
      <div style="position: relative; border-radius: 18px; overflow: hidden; aspect-ratio: 21 / 9; max-height: 310px; background: var(--orange-50); margin-bottom: var(--space-5);" data-profile-gallery>
        <img src="<?= View::e($coverImg) ?>" alt="<?= View::e($b['branch_name_en'] ?? '') ?>" data-gallery-main-img style="width: 100%; height: 100%; object-fit: cover; display: block; transition: opacity 180ms ease; cursor: pointer;">
        <span data-gallery-caption style="position: absolute; top: 0.75rem; left: 0.75rem; background: rgba(17,19,21,0.62); color: #FFFFFF; font-size: 0.72rem; font-weight: 600; padding: 0.25rem 0.65rem; border-radius: 999px; backdrop-filter: blur(6px);">
          <?= View::e($gallerySlides[0]['label']) ?>
        </span>
        <div style="position: absolute; bottom: 0.75rem; left: 50%; transform: translateX(-50%); display: flex; gap: 0.45rem; background: rgba(17,19,21,0.48); padding: 0.32rem 0.7rem; border-radius: 999px; backdrop-filter: blur(6px);">
          <?php foreach ($gallerySlides as $idx => $slide): ?>
            <button type="button"
                    data-gallery-dot="<?= $idx ?>"
                    data-gallery-src="<?= View::e($slide['src']) ?>"
                    data-gallery-label="<?= View::e($slide['label']) ?>"
                    aria-label="View photo <?= $idx + 1 ?>"
                    style="width: 9px; height: 9px; border-radius: 50%; border: none; padding: 0; cursor: pointer; background: <?= $idx === 0 ? '#FFFFFF' : 'rgba(255,255,255,0.45)' ?>;"></button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Title, Availability & Rating Row (Cloned from Image 1 "Rice Seeds" Details layout) -->
      <div style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: var(--space-4); align-items: flex-start;">
        <div>
          <h1 style="font-size: clamp(1.35rem, 2.8vw, 2.1rem);"><?= View::e($b['branch_name_en'] ?? '') ?></h1>
          <?php if (!empty($b['branch_name_am']) || !empty($b['business_name_am'])): ?>
            <div class="b-lang-am" lang="am" style="font-size: var(--text-sm); color: var(--neutral-500); margin-top: 0.15rem;">
              <?= View::e($b['branch_name_am'] ?? $b['business_name_am']) ?>
            </div>
          <?php endif; ?>

          <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem; margin-top: var(--space-2);">
            <span class="b-status-pill b-status-pill--<?= View::e($openNow['css_modifier']) ?>">
              <span class="b-status-pill__dot"></span>
              <span><?= View::e($openNow['badge_label']) ?></span>
            </span>

            <?php if ((int) ($b['published_review_count'] ?? 0) > 0): ?>
              <span class="b-rating b-tabular" style="font-size: var(--text-sm);">
                <span class="b-rating__star">&#9733;</span>
                <span><?= number_format((float) $b['published_rating_avg'], 1) ?></span>
                <span style="color: var(--neutral-500); font-weight: 400;">(<?= (int) $b['published_review_count'] ?>)</span>
              </span>
            <?php endif; ?>
          </div>
        </div>

        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.4rem;">
          <?= View::component('trust-badge', [
              'isVerified' => !empty($b['is_verified']),
              'verifiedAt' => $b['last_verified_at'] ?? null,
          ]) ?>
          <span class="b-badge b-badge--orange"><?= View::e($b['area_name_en'] ?? 'Bole Bulbula') ?></span>
        </div>
      </div>

      <!-- Description, Multi-Branch Switcher (C-08) & Offerings (D-44) -->
      <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--color-border-subtle);">
        <?php if (!empty($b['sibling_branches']) && count($b['sibling_branches']) > 1): ?>
          <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.65rem; padding: var(--space-3) var(--space-4); background: var(--orange-50); border: 1px solid var(--orange-200); border-radius: var(--radius-md); margin-bottom: var(--space-4);">
            <span style="font-size: var(--text-xs); font-weight: 700; color: var(--orange-700);">
              Switch Branch Location (<?= count($b['sibling_branches']) ?> Branches in Bole Bulbula):
            </span>
            <select class="b-select" data-branch-switcher aria-label="Switch branch location" style="max-width: 280px; min-height: 36px; font-size: var(--text-xs);">
              <?php foreach ($b['sibling_branches'] as $sib): ?>
                <?php $sibUrl = '/b/' . ($b['business_slug'] ?? '') . '/' . ($sib['branch_slug'] ?? ''); ?>
                <option value="<?= View::e($sibUrl) ?>" <?= ($sib['branch_slug'] ?? '') === ($b['branch_slug'] ?? '') ? 'selected' : '' ?>>
                  <?= View::e($sib['branch_name_en']) ?> — <?= View::e($sib['area_name_en'] ?? 'Bole Bulbula') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <h2 style="font-size: var(--text-base); margin-bottom: 0.35rem;">Description</h2>
        <p style="font-size: var(--text-sm); color: var(--neutral-600); line-height: 1.6;">
          <?= View::e($b['brand_summary_en'] ?? '') ?>
        </p>

        <?php if (!empty($b['services_list']) || !empty($b['secondary_categories'])): ?>
          <div style="display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: var(--space-3);">
            <?php foreach (($b['secondary_categories'] ?? []) as $secCat): ?>
              <a href="/c/<?= View::e($secCat['slug']) ?>" class="b-badge b-badge--orange">
                <?= View::e($secCat['name_en']) ?>
              </a>
            <?php endforeach; ?>
            <?php foreach (($b['services_list'] ?? []) as $srv): ?>
              <span class="b-subcat-pill"><?= View::e(is_scalar($srv) ? (string) $srv : '') ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Direct Action Bar (C-10) -->
      <div class="b-action-bar">
        <?php if (!empty($b['phone_primary'])): ?>
          <a href="tel:<?= View::e($b['phone_primary']) ?>"
             class="b-btn b-btn--primary"
             data-branch-action="call"
             data-action-endpoint="<?= View::e($actionEndpoint) ?>">
            <span>Call <?= View::e($b['phone_primary']) ?></span>
          </a>
        <?php endif; ?>

        <?php if (!empty($b['directions_url'])): ?>
          <a href="<?= View::e($b['directions_url']) ?>"
             target="_blank"
             rel="noopener noreferrer"
             class="b-btn b-btn--outline"
             data-branch-action="directions"
             data-action-endpoint="<?= View::e($actionEndpoint) ?>">
            <span>Get Directions</span>
          </a>
        <?php endif; ?>

        <?php if (!empty($b['telegram_handle'])): ?>
          <a href="https://t.me/<?= View::e(ltrim((string) $b['telegram_handle'], '@')) ?>"
             target="_blank"
             rel="noopener noreferrer"
             class="b-btn b-btn--outline"
             data-branch-action="telegram"
             data-action-endpoint="<?= View::e($actionEndpoint) ?>">
            <span>Telegram (<?= View::e($b['telegram_handle']) ?>)</span>
          </a>
        <?php endif; ?>

        <button type="button"
                class="b-btn b-btn--ghost-orange"
                data-share-btn
                data-share-title="<?= View::e($b['branch_name_en'] ?? '') ?>"
                data-share-url="<?= View::e($canonicalPath) ?>"
                data-branch-action="share"
                data-action-endpoint="<?= View::e($actionEndpoint) ?>">
          <span>Share</span>
        </button>
      </div>
    </div>

    <!-- 2-Column Detail Layout -->
    <div class="b-profile-grid">
      <div style="display: flex; flex-direction: column; gap: var(--space-5);">
        <div class="b-card" style="padding: var(--space-5);">
          <h2 style="font-size: var(--text-lg); margin-bottom: var(--space-2);">Location &amp; Landmark</h2>
          <p style="color: var(--neutral-800); font-size: var(--text-sm); font-weight: 500;">
            <?= View::e($b['address_line_en'] ?? ($b['address_text_en'] ?? '')) ?>
          </p>
          <?php $landmarkText = $b['landmark_notes_en'] ?? ($b['landmark_en'] ?? ''); ?>
          <?php if (!empty($landmarkText)): ?>
            <div style="margin-top: var(--space-3); padding: var(--space-3) var(--space-4); background: var(--orange-50); border-radius: var(--radius-md); font-size: var(--text-xs); color: var(--neutral-800);">
              <strong style="color: var(--orange-700);">Landmark:</strong> <?= View::e((string) $landmarkText) ?>
            </div>
          <?php endif; ?>

          <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; margin-top: var(--space-3); padding-top: var(--space-3); border-top: 1px solid var(--color-border-subtle);">
            <?php if (!empty($b['area_slug'])): ?>
              <a href="/a/<?= View::e((string) $b['area_slug']) ?>" class="b-subcat-pill">
                Corridor: <?= View::e($b['area_name_en'] ?? 'Bole Bulbula') ?> &rarr;
              </a>
            <?php endif; ?>
            <?php if (!empty($b['latitude']) && !empty($b['longitude'])): ?>
              <a href="/nearby?lat=<?= View::e((string) $b['latitude']) ?>&amp;lng=<?= View::e((string) $b['longitude']) ?>&amp;radius=1500"
                 style="font-size: var(--text-xs); font-weight: 700; color: var(--orange-500);">
                See Nearby Within 1.5 km &rarr;
              </a>
            <?php endif; ?>
          </div>
        </div>

        <div class="b-card" style="padding: var(--space-5);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
            <h2 style="font-size: var(--text-lg);">Opening Hours</h2>
            <span style="font-size: var(--text-xs); color: var(--neutral-500);">Addis Ababa (EAT)</span>
          </div>

          <p style="font-size: var(--text-xs); color: var(--neutral-600); margin-bottom: var(--space-3);">
            <?= View::e($openNow['detail_label'] ?? ($openNow['reason'] ?? '')) ?>
          </p>

          <?php $weeklyHours = $b['weekly_hours'] ?? []; ?>
          <?php if (empty($weeklyHours)): ?>
            <p style="font-size: var(--text-sm);">Hours have not yet been verified for this branch.</p>
          <?php else: ?>
            <table class="b-hours-table">
              <tbody>
                <?php
                  $eatNow   = new DateTimeImmutable('now', new DateTimeZone('Africa/Addis_Ababa'));
                  $todayKey = strtolower($eatNow->format('D'));
                ?>
                <?php foreach ($weeklyHours as $dayKey => $row): ?>
                  <?php $isToday = ($dayKey === $todayKey); ?>
                  <tr class="<?= $isToday ? 'is-today' : '' ?>">
                    <td><?= View::e($row['day'] ?? '') ?><?= $isToday ? ' (Today)' : '' ?></td>
                    <td style="text-align: right;" class="b-tabular"><?= View::e($row['hours'] ?? 'Not recorded') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <!-- Reviews (C-13) -->
        <div class="b-card" style="padding: var(--space-5);">
          <h2 style="font-size: var(--text-lg); margin-bottom: var(--space-3);">Customer Reviews</h2>

          <?php if (empty($reviews)): ?>
            <p style="font-size: var(--text-sm); margin-bottom: var(--space-4);">No published reviews yet.</p>
          <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: var(--space-3); margin-bottom: var(--space-4);">
              <?php foreach ($reviews as $rev): ?>
                <div style="padding: var(--space-3); background: var(--orange-25); border: 1px solid var(--color-border-subtle); border-radius: var(--radius-md);">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                    <strong style="font-size: var(--text-xs); color: var(--neutral-900);"><?= View::e($rev['customer_name'] ?? ($rev['author_name'] ?? 'Verified Customer')) ?></strong>
                    <span class="b-rating b-tabular">
                      <span class="b-rating__star">&#9733;</span> <?= (int) $rev['rating'] ?>/5
                    </span>
                  </div>
                  <?php if (!empty($rev['title'])): ?>
                    <div style="font-weight: 600; font-size: var(--text-xs); color: var(--neutral-900);"><?= View::e($rev['title']) ?></div>
                  <?php endif; ?>
                  <p style="font-size: var(--text-xs); margin-top: 0.2rem;"><?= View::e($rev['review_text'] ?? ($rev['body'] ?? '')) ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div style="border-top: 1px solid var(--color-border-subtle); padding-top: var(--space-4);">
            <h3 style="font-size: var(--text-sm); margin-bottom: var(--space-2);">Write a Review</h3>
            <?php if (!empty($customer)): ?>
              <form action="/account/reviews/submit" method="POST" style="display: flex; flex-direction: column; gap: var(--space-3);">
                <?= View::csrfField() ?>
                <input type="hidden" name="branch_id" value="<?= (int) ($b['id'] ?? 0) ?>">
                <input type="hidden" name="return_to" value="<?= View::e($canonicalPath) ?>">
                <div>
                  <label class="b-label" for="rev-rating">Rating</label>
                  <select id="rev-rating" name="rating" class="b-select" required>
                    <option value="5">5 Stars — Excellent</option>
                    <option value="4">4 Stars — Good</option>
                    <option value="3">3 Stars — Average</option>
                    <option value="2">2 Stars — Below Average</option>
                    <option value="1">1 Star — Poor</option>
                  </select>
                </div>
                <div>
                  <label class="b-label" for="rev-title">Summary</label>
                  <input id="rev-title" type="text" name="title" class="b-input" placeholder="Short summary of your visit" required>
                </div>
                <div>
                  <label class="b-label" for="rev-body">Experience</label>
                  <textarea id="rev-body" name="body" class="b-textarea" placeholder="Share helpful details..." required></textarea>
                </div>
                <button type="submit" class="b-btn b-btn--primary">Submit Review</button>
              </form>
            <?php else: ?>
              <p style="font-size: var(--text-xs); margin-bottom: var(--space-3);">Sign in to submit a review.</p>
              <a href="/auth/login" class="b-btn b-btn--outline b-btn--sm">Sign In to Review</a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Right Column: Report Issue (C-14) -->
      <div>
        <div class="b-card" style="padding: var(--space-5);">
          <h2 style="font-size: var(--text-lg); margin-bottom: var(--space-2);">Report an Update</h2>
          <p style="font-size: var(--text-xs); margin-bottom: var(--space-4);">
            Notice a changed phone number, updated hours, or relocation? Let our operations team know so we can re-verify this branch.
          </p>

          <form action="/reports/submit" method="POST" style="display: flex; flex-direction: column; gap: var(--space-3);">
            <?= View::csrfField() ?>
            <input type="hidden" name="branch_id" value="<?= (int) ($b['id'] ?? 0) ?>">
            <input type="hidden" name="return_to" value="<?= View::e($canonicalPath) ?>">
            <div>
              <label class="b-label" for="rep-type">What needs updating?</label>
              <select id="rep-type" name="issue_type" class="b-select" required>
                <option value="wrong_hours">Opening Hours Changed</option>
                <option value="wrong_phone">Phone Number Unreachable</option>
                <option value="wrong_pin">Location / Landmark Correction</option>
                <option value="closed_branch">Branch Closed or Relocated</option>
                <option value="other">Other Correction</option>
              </select>
            </div>
            <div>
              <label class="b-label" for="rep-desc">Details</label>
              <textarea id="rep-desc" name="description" class="b-textarea" placeholder="What should our team verify?" required></textarea>
            </div>
            <div>
              <label class="b-label" for="rep-contact">Your Phone or Telegram (Optional)</label>
              <input id="rep-contact" type="text" name="reporter_contact" class="b-input" placeholder="+251 9... or @username">
            </div>
            <button type="submit" class="b-btn b-btn--outline">Send Report</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
