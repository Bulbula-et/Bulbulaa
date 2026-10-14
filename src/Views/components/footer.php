<?php
declare(strict_types=1);
use Bulbula\Core\View;
?>
<footer class="b-footer">
  <div class="b-container">
    <div class="b-footer__grid">
      <!-- Brand Column -->
      <div class="b-footer__brand">
        <div style="display: flex; align-items: center; gap: 0.65rem;">
          <svg width="32" height="32" viewBox="0 0 40 40" fill="none" aria-hidden="true">
            <rect width="40" height="40" rx="10" fill="#EA6A15"/>
            <path d="M13 11H21.2C24.4 11 26.5 12.6 26.5 15.2C26.5 17.1 25.3 18.4 23.6 18.9C25.8 19.4 27.2 21 27.2 23.3C27.2 26.3 24.8 28 21.1 28H13V11ZM16.8 17.6H20.4C21.9 17.6 22.8 16.8 22.8 15.6C22.8 14.4 21.9 13.7 20.4 13.7H16.8V17.6ZM16.8 25.2H20.8C22.5 25.2 23.5 24.4 23.5 23C23.5 21.6 22.5 20.8 20.8 20.8H16.8V25.2Z" fill="#FFFFFF"/>
          </svg>
          <span style="font-size: 1.2rem; font-weight: 700; color: #FFFFFF; letter-spacing: -0.02em;">
            Bulbula<span style="color: var(--orange-400);">.et</span>
          </span>
        </div>
        <p>
          Bole Bulbula’s company-operated local business discovery platform. Every listing, opening hour, and phone number is verified by our operations team in Addis Ababa.
        </p>
      </div>

      <!-- Discovery Column -->
      <div class="b-footer__col">
        <h4>Explore</h4>
        <ul>
          <li><a href="/">Discover Home</a></li>
          <li><a href="/search">Search Directory</a></li>
          <li><a href="/categories">Categories</a></li>
          <li><a href="/areas">Bole Bulbula Areas</a></li>
          <li><a href="/nearby">Nearby Discovery</a></li>
        </ul>
      </div>

      <!-- Trust & Policies Column -->
      <div class="b-footer__col">
        <h4>Trust &amp; Governance</h4>
        <ul>
          <li><a href="/about">About Bulbula.et</a></li>
          <li><a href="/policies/verification">Verification Standard</a></li>
          <li><a href="/policies/sponsorship">Sponsored Separation</a></li>
          <li><a href="/policies/reviews">Review Moderation</a></li>
        </ul>
      </div>

      <!-- Account & Operations Column -->
      <div class="b-footer__col">
        <h4>Access</h4>
        <ul>
          <li><a href="/auth/login">Customer Sign In</a></li>
          <li><a href="/account/saved">Saved Places</a></li>
          <li><a href="/nearby?lat=8.9538&amp;lng=38.7892&amp;radius=3500">Nearby Radar</a></li>
          <li><a href="/staff" style="color: var(--orange-300); font-weight: 500;">Staff Console &rarr;</a></li>
        </ul>
      </div>
    </div>

    <div class="b-footer__bottom">
      <div>&copy; <?= date('Y') ?> Bulbula.et &middot; Bole Bulbula, Addis Ababa, Ethiopia</div>
      <div>Verified Local Discovery &middot; Organic Ranking Integrity</div>
    </div>
  </div>
</footer>
