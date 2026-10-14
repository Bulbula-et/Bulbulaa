<?php
declare(strict_types=1);
use Bulbula\Core\Session;
use Bulbula\Core\View;

$flashSuccess = Session::flash('success');
$flashError   = Session::flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#FFFAF5">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <title><?= View::e($pageTitle ?? 'Bulbula.et — Bole Bulbula Local Business Directory') ?></title>
  <meta name="description" content="<?= View::e($metaDescription ?? 'Verified local business directory for Bole Bulbula, Addis Ababa.') ?>">
  <?php if (!empty($canonicalUrl)): ?>
    <link rel="canonical" href="<?= View::e($canonicalUrl) ?>">
  <?php endif; ?>

  <!-- Unified Design System CSS (Automatic ?v=filemtime() Cache-Busting) -->
  <link rel="stylesheet" href="<?= View::asset('css/tokens.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('css/layout.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('css/components.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('css/futuristic.css') ?>">
</head>
<body class="b-app-shell">
  <?= View::component('header', [
      'request'  => $request ?? null,
      'customer' => $customer ?? null,
  ]) ?>

  <main class="b-main">
    <?php if ($flashSuccess !== null || $flashError !== null): ?>
      <div class="b-container" style="padding-top: var(--space-4);">
        <?php if ($flashSuccess !== null): ?>
          <div class="b-alert b-alert--success" role="status">
            <span><?= View::e($flashSuccess) ?></span>
          </div>
        <?php endif; ?>
        <?php if ($flashError !== null): ?>
          <div class="b-alert b-alert--error" role="alert">
            <span><?= View::e($flashError) ?></span>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?= $content ?? '' ?>
  </main>

  <?= View::component('footer', [
      'request'  => $request ?? null,
      'customer' => $customer ?? null,
  ]) ?>

  <!-- Unified Futuristic Responsive Bottom Navigation Dock (Web + Telegram Mini App + Tablet) -->
  <?= View::component('bottom-nav', [
      'request'  => $request ?? null,
      'customer' => $customer ?? null,
  ]) ?>

  <!-- Self-Hosted GSAP + Progressive Enhancement Modules -->
  <script src="<?= View::asset('js/vendor/gsap.min.js') ?>" defer></script>
  <script src="<?= View::asset('js/core/surface-adapter.js') ?>" defer></script>
  <script src="<?= View::asset('js/core/motion.js') ?>" defer></script>
  <script src="<?= View::asset('js/modules/autocomplete.js') ?>" defer></script>
  <script src="<?= View::asset('js/modules/nearby.js') ?>" defer></script>
  <script src="<?= View::asset('js/modules/modal-drawer.js') ?>" defer></script>
  <script src="<?= View::asset('js/modules/branch-switcher.js') ?>" defer></script>
</body>
</html>
