<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$canManage = !empty($canManage);
$staffUsers = $staffUsers ?? [];
$activeUsers = 0;
$administrators = 0;
$protectedUsers = 0;
foreach ($staffUsers as $user) {
    if (!empty($user['is_active'])) $activeUsers++;
    if (($user['role'] ?? '') === 'ADMINISTRATOR') $administrators++;
    if (!empty($user['totp_enabled'])) $protectedUsers++;
}
$initials = static function (string $name): string {
    $value = '';
    foreach (preg_split('/\s+/', trim($name)) ?: [] as $part) $value .= mb_substr($part, 0, 1);
    return mb_strtoupper(mb_substr($value, 0, 2));
};
?>
<?php if (!$canManage): ?>
  <section class="b-access-denied"><strong>Administrator authorization required</strong><p>Gate D-45 restricts Staff identity, role, password, and authenticator controls to authenticated Administrators.</p></section>
<?php else: ?>
  <section class="b-access-boundary">
    <div class="b-access-boundary__main"><h2>Staff identity and access control</h2><p>Manage operational identities through explicit roles, individual credentials, account status, and authenticator enrollment. All changes remain attributable in the security audit log.</p><div class="b-access-boundary__controls"><span class="b-access-boundary__control">Gate D-45</span><span class="b-access-boundary__control">Role-based access</span><span class="b-access-boundary__control">Individual accounts only</span><span class="b-access-boundary__control">2FA protected</span></div></div>
    <article class="b-access-stat"><span class="b-access-stat__label">Active staff</span><strong class="b-access-stat__value"><?= $activeUsers ?></strong><span class="b-access-stat__meta">of <?= count($staffUsers) ?> total accounts</span></article>
    <article class="b-access-stat"><span class="b-access-stat__label">Administrators</span><strong class="b-access-stat__value"><?= $administrators ?></strong><span class="b-access-stat__meta">Full access and approvals</span></article>
    <article class="b-access-stat"><span class="b-access-stat__label">2FA protected</span><strong class="b-access-stat__value"><?= $protectedUsers ?></strong><span class="b-access-stat__meta">Authenticator enrollment complete</span></article>
  </section>

  <section class="b-card b-user-create">
    <header class="b-user-create__head"><h2>Create a Staff identity</h2><p>Issue a unique account and minimum required role. The staff member should replace the initial password securely.</p></header>
    <form action="/staff/users/create" method="POST" class="b-user-form" autocomplete="off">
      <?= Csrf::field() ?>
      <div><label class="b-label" for="stf-name">Full name</label><input id="stf-name" type="text" name="full_name" class="b-input" placeholder="e.g. Hanna Girma" autocomplete="off" required></div>
      <div><label class="b-label" for="stf-email">Staff email</label><input id="stf-email" type="email" name="email" class="b-input" placeholder="name@yourcompany.com" autocomplete="off" required></div>
      <div><label class="b-label" for="stf-role">Access role</label><select id="stf-role" name="role" class="b-select"><option value="OPERATOR">Operator — field and queue operations</option><option value="ADMINISTRATOR">Administrator — full access and approvals</option></select></div>
      <div><label class="b-label" for="stf-pass">Initial password</label><input id="stf-pass" type="password" name="password" class="b-input" placeholder="Minimum 8 characters" autocomplete="new-password" minlength="8" required></div>
      <div><button type="submit" class="b-btn b-btn--primary b-btn--sm">Create Staff account</button></div>
    </form>
  </section>

  <section class="b-card b-user-register">
    <header class="b-user-register__head"><h2>Staff access register</h2><span>Identity, role, account state, and authenticator controls</span></header>
    <?php if ($staffUsers === []): ?>
      <div class="b-user-empty"><strong>No Staff accounts</strong>Create the first controlled Staff identity to begin operations.</div>
    <?php else: ?>
      <div class="b-table-wrap" style="border:0;border-radius:0;box-shadow:none"><table class="b-table b-user-table"><thead><tr><th>Staff identity</th><th>Role</th><th>Account</th><th>Security</th><th>Last login</th><th>Administrative controls</th></tr></thead><tbody>
        <?php foreach ($staffUsers as $user): ?>
          <tr>
            <td><div class="b-user-identity"><span class="b-user-avatar"><?= View::e($initials((string) $user['full_name'])) ?></span><div><strong><?= View::e($user['full_name']) ?></strong><span><?= View::e($user['email']) ?></span></div></div></td>
            <td><span class="b-badge <?= ($user['role'] ?? '') === 'ADMINISTRATOR' ? 'b-badge--blue' : 'b-badge--orange' ?>"><?= View::e(ucfirst(strtolower((string) $user['role']))) ?></span></td>
            <td><span class="b-badge <?= !empty($user['is_active']) ? 'b-badge--verified' : 'b-badge--orange' ?>"><?= !empty($user['is_active']) ? 'Active' : 'Suspended' ?></span></td>
            <td><div class="b-user-security"><span class="b-badge <?= !empty($user['totp_enabled']) ? 'b-badge--verified' : 'b-badge--orange' ?>"><?= !empty($user['totp_enabled']) ? '2FA protected' : '2FA pending' ?></span></div></td>
            <td><span class="b-user-last-login"><?= View::e($user['last_login_at'] ?? 'Never') ?></span></td>
            <td><div class="b-user-actions">
              <form action="/staff/users/<?= (int) $user['id'] ?>/password" method="POST" class="b-user-password" autocomplete="off"><?= Csrf::field() ?><input type="password" name="new_password" class="b-input" placeholder="New password" autocomplete="new-password" minlength="8" required aria-label="New password for <?= View::e($user['full_name']) ?>"><button type="submit" class="b-btn b-btn--outline b-btn--sm">Reset password</button></form>
              <?php if (!empty($user['totp_enabled'])): ?><form action="/staff/users/<?= (int) $user['id'] ?>/2fa-reset" method="POST" data-confirm="Reset this staff member’s authenticator enrollment?"><?= Csrf::field() ?><button type="submit" class="b-btn b-btn--outline b-btn--sm">Reset 2FA</button></form><?php endif; ?>
              <form action="/staff/users/<?= (int) $user['id'] ?>/toggle" method="POST"><?= Csrf::field() ?><button type="submit" class="b-btn b-btn--outline b-btn--sm"><?= !empty($user['is_active']) ? 'Suspend' : 'Activate' ?></button></form>
            </div></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>
<?php endif; ?>
