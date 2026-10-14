<?php
declare(strict_types=1);
use Bulbula\Core\Config;
use Bulbula\Core\Csrf;
use Bulbula\Core\Session;
use Bulbula\Core\View;

$activeNav = $activeNav ?? 'dashboard';
$staff = $staff ?? ['id' => 0, 'full_name' => 'Staff member', 'role' => 'OPERATOR'];
$flashSuccess = Session::flash('success');
$flashError = Session::flash('error');
$isAdmin = ($staff['role'] ?? '') === 'ADMINISTRATOR';
$isPreview = Config::previewFeaturesEnabled();
$initials = '';
foreach (preg_split('/\s+/', trim((string) ($staff['full_name'] ?? 'Staff'))) ?: [] as $part) $initials .= mb_substr($part, 0, 1);
$initials = mb_strtoupper(mb_substr($initials, 0, 2));
$icons = [
  'dashboard'=>'<path d="M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z"/>',
  'listings'=>'<path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/>',
  'verification'=>'<path d="m5 12 4 4L19 6M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z"/>',
  'taxonomy'=>'<path d="M4 4h6v6H4zM14 4h6v6h-6zM9 14h6v6H9zM7 10v2a2 2 0 0 0 2 2h3M17 10v2a2 2 0 0 1-2 2h-3"/>',
  'locations'=>'<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
  'moderation'=>'<path d="M4 5h16M4 12h10M4 19h7M18 15v6M15 18h6"/>',
  'reports'=>'<path d="M12 9v4m0 4h.01M10.3 3.7 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/>',
  'campaigns'=>'<path d="m3 11 18-7-7 18-3-8-8-3Zm8 3 4-4"/>',
  'analytics'=>'<path d="M4 20V10m6 10V4m6 16v-7m4 7H2"/>',
  'audit-logs'=>'<path d="M6 3h12v18H6zM9 8h6m-6 4h6m-6 4h4"/>',
  'users'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
  'settings'=>'<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
];
$icon = static fn(string $key): string => '<svg aria-hidden="true" viewBox="0 0 24 24">' . ($icons[$key] ?? '') . '</svg>';
$navGroups = [
  'Workspace' => [
    ['dashboard','/staff','Overview'], ['listings','/staff/listings','Listings'], ['verification','/staff/verification','Verification'],
  ],
  'Operations' => [
    ['moderation','/staff/moderation','Moderation'], ['reports','/staff/reports','Reports'], ['campaigns','/staff/campaigns','Campaigns'], ['analytics','/staff/analytics','Analytics'],
  ],
  'Directory setup' => [
    ['taxonomy','/staff/taxonomy','Taxonomy'], ['locations','/staff/locations','Locations'],
  ],
];
if ($isAdmin) $navGroups['Administration'] = [
  ['audit-logs','/staff/audit-logs','Audit log'], ['users','/staff/users','Staff & access'], ['settings','/staff/settings','System settings'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= View::e($pageTitle ?? 'Staff Console — Bulbula.et') ?></title>
  <meta name="robots" content="noindex, nofollow"><meta name="theme-color" content="#ffffff">
  <link rel="stylesheet" href="<?= View::asset('css/tokens.css') ?>"><link rel="stylesheet" href="<?= View::asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('css/layout.css') ?>"><link rel="stylesheet" href="<?= View::asset('css/components.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('css/staff.css') ?>">
  <?php if ($activeNav === 'listings'): ?><link rel="stylesheet" href="<?= View::asset('css/staff-listings.css') ?>"><?php endif; ?>
  <?php if ($activeNav === 'verification'): ?><link rel="stylesheet" href="<?= View::asset('css/staff-verification.css') ?>"><?php endif; ?>
  <?php if ($activeNav === 'moderation'): ?><link rel="stylesheet" href="<?= View::asset('css/staff-moderation.css') ?>"><?php endif; ?>
  <?php if ($activeNav === 'reports'): ?><link rel="stylesheet" href="<?= View::asset('css/staff-reports.css') ?>"><?php endif; ?>
  <?php if ($activeNav === 'campaigns'): ?><link rel="stylesheet" href="<?= View::asset('css/staff-campaigns.css') ?>"><?php endif; ?>
  <?php if ($activeNav === 'analytics'): ?><link rel="stylesheet" href="<?= View::asset('css/staff-analytics.css') ?>"><?php endif; ?>
  <?php if ($activeNav === 'taxonomy'): ?><link rel="stylesheet" href="<?= View::asset('css/staff-taxonomy.css') ?>"><?php endif; ?>
  <?php if ($activeNav === 'locations'): ?><link rel="stylesheet" href="<?= View::asset('css/staff-locations.css') ?>"><?php endif; ?>
  <?php if ($activeNav === 'audit-logs'): ?><link rel="stylesheet" href="<?= View::asset('css/staff-audit.css') ?>"><?php endif; ?>
  <?php if ($activeNav === 'users'): ?><link rel="stylesheet" href="<?= View::asset('css/staff-users.css') ?>"><?php endif; ?>
  <?php if ($activeNav === 'settings'): ?><link rel="stylesheet" href="<?= View::asset('css/staff-settings.css') ?>"><?php endif; ?>
</head>
<body class="b-staff-body">
  <div class="b-staff-shell">
    <aside class="b-staff-sidebar" id="staff-sidebar">
      <div class="b-staff-brand">
        <a href="/staff" class="b-staff-brand__mark"><img src="<?= View::asset('img/logo-temp.svg') ?>" alt="Bulbula.et"></a>
        <div><strong>Control Center</strong><span>Internal operations</span></div>
        <button class="b-sidebar-close" type="button" data-sidebar-close aria-label="Close navigation">×</button>
      </div>
      <nav class="b-staff-nav" aria-label="Staff navigation">
        <?php foreach ($navGroups as $group => $items): ?>
          <div class="b-staff-nav__group">
            <div class="b-staff-nav__label"><?= View::e($group) ?></div>
            <?php foreach ($items as [$key,$href,$label]): ?>
              <a href="<?= $href ?>" class="b-staff-nav__link <?= $activeNav === $key ? 'is-active' : '' ?>" <?= $activeNav === $key ? 'aria-current="page"' : '' ?>>
                <span class="b-staff-nav__icon"><?= $icon($key) ?></span><span><?= View::e($label) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </nav>
      <div class="b-staff-sidebar__footer">
        <a href="/" class="b-staff-public-link"><span>←</span> View public directory</a>
        <?php if ($isPreview): ?><div class="b-preview-switch"><span>Preview role</span><a href="/staff?switch_staff=1">Admin</a><a href="/staff?switch_staff=2">Operator</a></div><?php endif; ?>
      </div>
    </aside>
    <button class="b-sidebar-scrim" data-sidebar-close aria-label="Close navigation"></button>

    <div class="b-staff-workspace">
      <header class="b-staff-header">
        <div class="b-staff-header__left">
          <button class="b-mobile-menu" type="button" data-sidebar-open aria-label="Open navigation"><?= $icon('listings') ?></button>
          <div class="b-staff-breadcrumb"><span>Bulbula.et</span><span>/</span><strong><?= View::e(ucwords(str_replace('-', ' ', (string) $activeNav))) ?></strong></div>
        </div>
        <div class="b-staff-header__actions">
          <span class="b-system-status"><i></i> Systems operational</span>
          <a class="b-staff-account" href="/staff/profile" style="text-decoration:none" aria-label="Open my profile and security settings">
            <span class="b-staff-avatar"><?= View::e($initials) ?></span>
            <div><strong><?= View::e((string) ($staff['full_name'] ?? '')) ?></strong><span><?= $isAdmin ? 'Administrator' : 'Operator' ?> · 2FA protected</span></div>
          </a>
          <form method="POST" action="/staff/logout" class="b-logout-form"><?= Csrf::field() ?><button type="submit" title="Sign out">Sign out</button></form>
        </div>
      </header>

      <main class="b-staff-main">
        <div class="b-page-heading">
          <div><p class="b-page-eyebrow">Operations workspace</p><h1><?= View::e($pageTitle ?? 'Staff Operations Console') ?></h1></div>
          <?php if ($isAdmin): ?><form method="POST" action="/staff/system/flush-cache" class="b-cache-form"><?= Csrf::field() ?><button type="submit" class="b-btn b-btn--outline b-btn--sm" title="Refresh application cache without restarting the server">↻ Refresh system cache</button></form><?php endif; ?>
        </div>
        <?php if ($flashSuccess !== null): ?><div class="b-alert b-alert--success b-staff-flash" role="status"><?= View::e($flashSuccess) ?></div><?php endif; ?>
        <?php if ($flashError !== null): ?><div class="b-alert b-alert--error b-staff-flash" role="alert"><?= View::e($flashError) ?></div><?php endif; ?>
        <div class="b-staff-content"><?= $content ?? '' ?></div>
      </main>
    </div>
  </div>
  <script src="<?= View::asset('js/core/confirm-actions.js') ?>" defer></script>
  <script src="<?= View::asset('js/staff-shell.js') ?>" defer></script>
  <?php if (in_array($activeNav, ['dashboard', 'analytics', 'campaigns'], true)): ?>
    <script src="<?= View::asset('vendor/chartjs/chart.umd.min.js') ?>" defer></script>
    <script src="<?= View::asset('js/staff-charts.js') ?>" defer></script>
  <?php endif; ?>
</body>
</html>
