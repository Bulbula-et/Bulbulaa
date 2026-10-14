<?php
declare(strict_types=1);
use Bulbula\Core\View;

$isVerified = !empty($isVerified);
$verifiedAt = $verifiedAt ?? null;
?>
<?php if ($isVerified): ?>
<span class="b-badge b-badge--verified" title="Verified by Bulbula Staff<?= $verifiedAt ? ' on ' . View::e(substr((string) $verifiedAt, 0, 10)) : '' ?>">
  <svg width="12" height="12" viewBox="0 0 20 20" fill="none" aria-hidden="true">
    <circle cx="10" cy="10" r="9" fill="#1D4E89"/>
    <path d="M6.5 10.2L8.8 12.5L13.6 7.5" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
  <span>Verified</span>
</span>
<?php else: ?>
<span class="b-badge" style="background: var(--neutral-100); color: var(--neutral-600);">
  <span>Listed</span>
</span>
<?php endif; ?>
