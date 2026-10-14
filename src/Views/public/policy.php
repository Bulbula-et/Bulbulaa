<?php
declare(strict_types=1);
use Bulbula\Core\View;

$rawTopic = (string) ($policySlug ?? ($topic ?? 'about'));
$topic    = $rawTopic === 'sponsored' ? 'sponsorship' : $rawTopic;

$policyTabs = [
    'about'        => ['url' => '/about',                 'label' => 'About Bulbula.et'],
    'editorial'    => ['url' => '/policies/editorial',    'label' => 'Editorial Standards (C-19)'],
    'verification' => ['url' => '/policies/verification', 'label' => 'Verification & QC (C-12)'],
    'sponsorship'  => ['url' => '/policies/sponsorship',  'label' => 'Sponsored Separation (C-16)'],
    'reviews'      => ['url' => '/policies/reviews',      'label' => 'Review Moderation (C-13)'],
    'privacy'      => ['url' => '/policies/privacy',      'label' => 'Privacy & Data Rights (C-36)'],
    'terms'        => ['url' => '/policies/terms',        'label' => 'Terms of Use'],
];
?>
<section class="b-section b-section--tint" style="padding: var(--space-8) 0;">
  <div class="b-container">
    <div class="b-section__eyebrow">Trust &amp; Transparency (C-18)</div>
    <h1>Platform Standards &amp; Governance</h1>
    <p style="margin-top: var(--space-2);">
      How Bulbula.et verifies Bole Bulbula businesses, protects organic ranking integrity, and safeguards resident privacy.
    </p>

    <div style="display: flex; flex-wrap: wrap; gap: 0.45rem; margin-top: var(--space-5);">
      <?php foreach ($policyTabs as $key => $tab): ?>
        <?php $isActive = ($topic === $key); ?>
        <a href="<?= View::e($tab['url']) ?>"
           class="b-chip"
           style="<?= $isActive ? 'background: var(--orange-500); color: #FFFFFF; border-color: var(--orange-500);' : '' ?>">
          <?= View::e($tab['label']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="b-section">
  <div class="b-container" style="max-width: 860px;">
    <div class="b-card" style="padding: var(--space-8); display: flex; flex-direction: column; gap: var(--space-6);">
      <?php if ($topic === 'editorial'): ?>
        <div>
          <span class="b-badge b-badge--orange">Hard Gate C-19 &amp; C-20</span>
          <h2 style="margin-top: var(--space-2); margin-bottom: var(--space-2);">Permissioned Editorial Onboarding Standard</h2>
          <p>
            Bulbula.et is a company-operated local business directory focused on Bole Bulbula, Addis Ababa. Unlike scraped directories, no business or branch listing can be published without a linked, active <strong>GRANTED Business Permission Record</strong> collected by our Addis Ababa operations staff via recorded field visit, signed form, or verified representative confirmation.
          </p>
        </div>
        <div>
          <h3 style="margin-bottom: var(--space-2);">Business vs. Branch Fact Separation</h3>
          <p>
            Brand-level facts (English &amp; Amharic name, primary category, and brand summary) are stored on the parent Business, while physical operating facts (corridor area, landmark, GPS coordinates, phone number, opening hours, and reviews) belong strictly to each individual Branch.
          </p>
        </div>
      <?php elseif ($topic === 'verification'): ?>
        <div>
          <span class="b-badge b-badge--verified">Verified Badge Standard (C-12 &amp; C-21)</span>
          <h2 style="margin-top: var(--space-2); margin-bottom: var(--space-2);">Field Verification &amp; Quality Control Gate</h2>
          <p>
            The <strong>Verified</strong> badge is an operational trust indicator—it is never sold or bundled with commercial sponsorship. A branch earns and retains Verified status only after passing our multi-point Quality Control (`C-21`) checklist confirming its physical corridor location, reachable phone contact, 2-level category accuracy, and permission evidence.
          </p>
        </div>
        <div>
          <h3 style="margin-bottom: var(--space-2);">Honest Opening Hours (`C-09`)</h3>
          <p>
            Bulbula.et never guesses opening hours. Live <em>Open Now / Closed Now</em> badges in Addis Ababa time (`EAT`) appear only when structured weekly hours (`KNOWN`) have been verified by staff; otherwise the branch is transparently labeled <em>Hours Not Recorded</em> or <em>By Appointment</em>.
          </p>
        </div>
      <?php elseif ($topic === 'sponsorship'): ?>
        <div>
          <span class="b-badge b-badge--orange">Commercial Integrity (C-16, C-27, Gate D-39)</span>
          <h2 style="margin-top: var(--space-2); margin-bottom: var(--space-2);">Strict Separation of Sponsored &amp; Organic Results</h2>
          <p>
            Paid placements on Bulbula.et are limited to three clearly labeled formats: <strong>Homepage Promotion</strong>, <strong>Category Sponsorship</strong>, and <strong>Search Placement</strong>. Every paid placement is time-bounded, sold at a fixed ETB package price, and requires Administrator approval (`C-27`).
          </p>
        </div>
        <div>
          <h3 style="margin-bottom: var(--space-2);">What Sponsorship Never Does</h3>
          <p>
            Purchasing a sponsored placement never alters organic search ranking, never grants a Verified badge, and never suppresses or alters customer reviews.
          </p>
        </div>
      <?php elseif ($topic === 'reviews'): ?>
        <div>
          <span class="b-badge b-badge--orange">Community Feedback (C-13, C-25, C-35)</span>
          <h2 style="margin-top: var(--space-2); margin-bottom: var(--space-2);">Review Participation &amp; Pre-Publication Moderation</h2>
          <p>
            Authenticated customers can submit one active review per branch with an integer rating from <strong>1 to 5 stars</strong> and optional written commentary. All new and edited reviews enter our pre-publication moderation queue (`C-25`) before appearing publicly or affecting the branch's unweighted rating mean.
          </p>
        </div>
        <div>
          <h3 style="margin-bottom: var(--space-2);">30-Day Edit Window &amp; Author Withdrawal (`C-35`)</h3>
          <p>
            Customers may edit their review within 30 days of initial submission (which re-enters moderation) or withdraw their review at any time from their account page.
          </p>
        </div>
      <?php elseif ($topic === 'privacy'): ?>
        <div>
          <span class="b-badge b-badge--blue">Data Minimisation (C-30..C-36, C-40)</span>
          <h2 style="margin-top: var(--space-2); margin-bottom: var(--space-2);">Privacy, Password-Free Identity &amp; Account Rights</h2>
          <p>
            Bulbula.et uses password-free authentication (Email One-Time Passcodes and Google Sign-In) across both Web and Telegram Mini App surfaces. Guest visitors can search, browse categories, explore corridors, and submit listing correction reports without creating an account.
          </p>
        </div>
        <div>
          <h3 style="margin-bottom: var(--space-2);">First-Party Analytics &amp; Account Deletion (`C-36`, `C-38`)</h3>
          <p>
            We record only first-party operational metrics (profile views, contact clicks, and zero-result search queries) without third-party ad trackers. Customers can request an account data summary or full account deletion at any time from `/account`.
          </p>
        </div>
      <?php elseif ($topic === 'terms'): ?>
        <div>
          <span class="b-badge b-badge--orange">Terms of Use (C-18)</span>
          <h2 style="margin-top: var(--space-2); margin-bottom: var(--space-2);">Bulbula.et Directory Terms of Service</h2>
          <p>
            By using Bulbula.et on Web or Telegram Mini App, you agree to use directory contact details and landmarks for lawful personal discovery in Addis Ababa, and not to scrape, republish, or submit fraudulent reviews or false correction reports.
          </p>
        </div>
      <?php else: ?>
        <div>
          <span class="b-badge b-badge--orange">Bole Bulbula Pilot (C-01..C-40)</span>
          <h2 style="margin-top: var(--space-2); margin-bottom: var(--space-2);">1. Company-Operated Local Coverage</h2>
          <p>
            Bulbula.et is a company-operated local business discovery platform serving Bole Bulbula, Addis Ababa (93 Mazoria, Mariam Mazoria, Bulbula Condominiums, and Gorge / Airport Side). Every listing goes through permissioned onboarding (`C-19`), duplicate checking, and quality control (`C-21`) by our operations team.
          </p>
        </div>
        <div>
          <h2 style="margin-bottom: var(--space-2);">2. Operational Verification Standard</h2>
          <p>
            A branch receives the <strong>Verified</strong> badge only after our staff confirms its operating location, working phone contact, category classification, and structured opening hours.
          </p>
        </div>
        <div>
          <h2 style="margin-bottom: var(--space-2);">3. Strict Sponsored &amp; Organic Separation</h2>
          <p>
            Sponsored campaigns appear exclusively inside clearly labeled <strong>Sponsored</strong> containers. Commercial sponsorship never alters organic search order, verification badges, or published customer ratings.
          </p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
