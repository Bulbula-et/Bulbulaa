<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\Session;
use Bulbula\Core\View;

$activeNav    = $activeNav ?? 'dashboard';
$staff        = $staff ?? ['id' => 1, 'full_name' => 'Dawit Tadesse (Admin)', 'role' => 'ADMINISTRATOR'];
$flashSuccess = Session::flash('success');
$flashError   = Session::flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= View::e($pageTitle ?? 'Staff Console — Bulbula.et V1') ?></title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="<?= View::asset('css/tokens.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('css/layout.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('css/components.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('css/staff.css') ?>">
</head>
<body>
  <div class="b-staff-shell">
    <!-- Left Sidebar (Deep Navy #102438 + Orange Active Indicators) -->
    <aside class="b-staff-sidebar">
      <div>
        <div style="background: #FFFFFF; padding: 0.45rem 0.7rem; border-radius: var(--radius-sm); display: inline-block;">
          <img src="<?= View::asset('img/logo-temp.svg') ?>" alt="Bulbula.et" style="height: 30px; width: auto; display: block;">
        </div>
        <div style="margin-top: 0.6rem; font-size: var(--text-xs); color: var(--orange-300); font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;">
          Internal Operations Console (Web-Only)
        </div>

        <nav class="b-staff-nav" aria-label="Staff Operations Navigation">
          <a href="/staff" class="b-staff-nav__link <?= $activeNav === 'dashboard' ? 'is-active' : '' ?>">
            <span>Overview &amp; Gates</span>
            <span style="font-size: 0.7rem; opacity: 0.8;">D-31</span>
          </a>
          <a href="/staff/listings" class="b-staff-nav__link <?= $activeNav === 'listings' ? 'is-active' : '' ?>">
            <span>Listings &amp; Permissions</span>
            <span style="font-size: 0.7rem; opacity: 0.8;">C-19/20</span>
          </a>
          <a href="/staff/verification" class="b-staff-nav__link <?= $activeNav === 'verification' ? 'is-active' : '' ?>">
            <span>Verification &amp; QC Gate</span>
            <span style="font-size: 0.7rem; opacity: 0.8;">C-21</span>
          </a>
          <a href="/staff/taxonomy" class="b-staff-nav__link <?= $activeNav === 'taxonomy' ? 'is-active' : '' ?>">
            <span>2-Level Taxonomy</span>
            <span style="font-size: 0.7rem; opacity: 0.8;">C-23</span>
          </a>
          <a href="/staff/locations" class="b-staff-nav__link <?= $activeNav === 'locations' ? 'is-active' : '' ?>">
            <span>Areas &amp; Locations</span>
            <span style="font-size: 0.7rem; opacity: 0.8;">C-24</span>
          </a>
          <a href="/staff/moderation" class="b-staff-nav__link <?= $activeNav === 'moderation' ? 'is-active' : '' ?>">
            <span>Review Moderation</span>
            <span style="font-size: 0.7rem; opacity: 0.8;">C-25</span>
          </a>
          <a href="/staff/reports" class="b-staff-nav__link <?= $activeNav === 'reports' ? 'is-active' : '' ?>">
            <span>Public Reports Queue</span>
            <span style="font-size: 0.7rem; opacity: 0.8;">C-26</span>
          </a>
          <a href="/staff/campaigns" class="b-staff-nav__link <?= $activeNav === 'campaigns' ? 'is-active' : '' ?>">
            <span>Sponsorship Campaigns</span>
            <span style="font-size: 0.7rem; opacity: 0.8;">C-27</span>
          </a>
          <a href="/staff/analytics" class="b-staff-nav__link <?= $activeNav === 'analytics' ? 'is-active' : '' ?>">
            <span>Operational Analytics</span>
            <span style="font-size: 0.7rem; opacity: 0.8;">C-28</span>
          </a>
          <a href="/staff/audit-logs" class="b-staff-nav__link <?= $activeNav === 'audit-logs' ? 'is-active' : '' ?>">
            <span>Audit Logs (Admin)</span>
            <span style="font-size: 0.7rem; opacity: 0.8;">C-29</span>
          </a>
        </nav>
      </div>

      <div style="margin-top: var(--space-8); padding-top: var(--space-4); border-top: 1px solid rgba(255,255,255,0.12); font-size: var(--text-xs);">
        <div style="color: var(--blue-200);">Signed in Staff (D-45 2FA):</div>
        <div style="font-weight: 700; color: #FFF; margin-top: 0.2rem;"><?= View::e($staff['full_name'] ?? '') ?></div>
        <div style="margin-top: 0.2rem;">
          <span class="b-badge b-badge--orange"><?= View::e($staff['role'] ?? 'ADMINISTRATOR') ?></span>
        </div>
        <div style="margin-top: 0.75rem; display: flex; gap: 0.4rem; flex-wrap: wrap;">
          <a href="/staff?switch_staff=1" style="color: var(--blue-200); text-decoration: underline;">Admin Role</a> &middot;
          <a href="/staff?switch_staff=2" style="color: var(--blue-200); text-decoration: underline;">Operator Role</a>
        </div>
        <div style="margin-top: 0.85rem;">
          <a href="/" class="b-btn b-btn--outline b-btn--sm" style="width: 100%;">&larr; Public Directory</a>
        </div>
      </div>
    </aside>

    <!-- Main Console Workspace -->
    <div class="b-staff-main">
      <div class="b-staff-topbar">
        <div>
          <h1 style="font-size: var(--text-xl);"><?= View::e($pageTitle ?? 'Staff Operations Console') ?></h1>
          <p style="font-size: var(--text-xs); color: var(--neutral-600);">
            Company-operated directory workflow &middot; Business permission (C-19) &amp; QC check (C-21) required before publication
          </p>
        </div>

        <!-- Zero-Restart Cache & OPcache Refresh Button -->
        <form method="POST" action="/staff/system/flush-cache" style="display: inline-flex; align-items: center; gap: 0.5rem;">
          <?= Csrf::field() ?>
          <button type="submit" class="b-btn b-btn--primary b-btn--sm" title="Flushes file cache & invalidates PHP OPcache without needing a server restart">
            <span>&#8635; Refresh Cache &amp; OPcache (Zero-Restart)</span>
          </button>
        </form>
      </div>

      <?php if ($flashSuccess !== null): ?>
        <div class="b-alert b-alert--success"><?= View::e($flashSuccess) ?></div>
      <?php endif; ?>
      <?php if ($flashError !== null): ?>
        <div class="b-alert b-alert--error"><?= View::e($flashError) ?></div>
      <?php endif; ?>

      <?= $content ?? '' ?>
    </div>
  </div>
</body>
</html>
