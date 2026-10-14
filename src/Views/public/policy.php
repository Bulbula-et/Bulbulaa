<?php
declare(strict_types=1);
use Bulbula\Core\View;

$topic = $topic ?? 'about';
?>
<section class="b-section b-section--tint" style="padding: var(--space-8) 0;">
  <div class="b-container">
    <div class="b-section__eyebrow">Trust &amp; Transparency</div>
    <h1>Platform Standards &amp; Governance</h1>
    <p style="margin-top: var(--space-2);">
      How Bulbula.et verifies local businesses, protects organic ranking integrity, and moderates community feedback.
    </p>

    <div style="display: flex; flex-wrap: wrap; gap: 0.45rem; margin-top: var(--space-5);">
      <a href="/about" class="b-chip" style="<?= $topic === 'about' ? 'background: var(--orange-600); color: #fff; border-color: var(--orange-600);' : '' ?>">About Bulbula.et</a>
      <a href="/policies/verification" class="b-chip" style="<?= $topic === 'verification' ? 'background: var(--orange-600); color: #fff; border-color: var(--orange-600);' : '' ?>">Verification Standard</a>
      <a href="/policies/sponsorship" class="b-chip" style="<?= $topic === 'sponsorship' ? 'background: var(--orange-600); color: #fff; border-color: var(--orange-600);' : '' ?>">Sponsored Separation</a>
      <a href="/policies/reviews" class="b-chip" style="<?= $topic === 'reviews' ? 'background: var(--orange-600); color: #fff; border-color: var(--orange-600);' : '' ?>">Review Moderation</a>
    </div>
  </div>
</section>

<section class="b-section">
  <div class="b-container" style="max-width: 840px;">
    <div class="b-card" style="padding: var(--space-8);">
      <h2 style="margin-bottom: var(--space-3);">1. Staff-Verified Local Coverage</h2>
      <p style="margin-bottom: var(--space-6);">
        Bulbula.et is a company-operated local business discovery platform serving Bole Bulbula, Addis Ababa. Every listing goes through structured onboarding, duplicate checking, and freshness review by our operations team.
      </p>

      <h2 style="margin-bottom: var(--space-3);">2. Verification Standard</h2>
      <p style="margin-bottom: var(--space-6);">
        A branch receives the <strong>Verified</strong> badge only after our staff confirms its operating location, working phone contact, category classification, and structured opening hours. Freshness cadence timers automatically alert our team when a branch is due for re-verification.
      </p>

      <h2 style="margin-bottom: var(--space-3);">3. Strict Sponsored &amp; Organic Separation</h2>
      <p style="margin-bottom: var(--space-6);">
        Sponsored campaigns appear exclusively inside clearly labeled <strong>Sponsored</strong> containers. Commercial sponsorship never alters organic search order, verification badges, or published customer ratings.
      </p>

      <h2 style="margin-bottom: var(--space-3);">4. Review &amp; Correction Governance</h2>
      <p>
        Customer reviews are held in a moderation queue before publication to prevent spam or fraudulent ratings. Visitor-submitted correction reports immediately open an operational ticket for our verification staff.
      </p>
    </div>
  </div>
</section>
