<?php
declare(strict_types=1);
use Bulbula\Core\Csrf;
use Bulbula\Core\View;

$canManage  = !empty($canManage);
$staffUsers = $staffUsers ?? [];
?>
<?php if (!$canManage): ?>
  <div class="b-alert b-alert--error">
    <span>Gate D-45 Security Boundary: Staff Account &amp; RBAC management is restricted to the ADMINISTRATOR role.</span>
  </div>
<?php else: ?>
  <div class="b-card" style="margin-bottom: var(--space-5); padding: var(--space-4);">
    <h2 style="font-size: var(--text-base); margin-bottom: var(--space-3);">Create Staff Account (Gate D-45 RBAC)</h2>
    <form action="/staff/users/create" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(175px, 1fr)); gap: var(--space-3); align-items: end;">
      <?= Csrf::field() ?>
      <div>
        <label class="b-label" for="stf-name">Full Name</label>
        <input id="stf-name" type="text" name="full_name" class="b-input" placeholder="e.g. Hanna Girma" required>
      </div>
      <div>
        <label class="b-label" for="stf-email">Staff Email</label>
        <input id="stf-email" type="email" name="email" class="b-input" placeholder="hanna@bulbula.et" required>
      </div>
      <div>
        <label class="b-label" for="stf-role">Role</label>
        <select id="stf-role" name="role" class="b-select">
          <option value="OPERATOR">OPERATOR (Field &amp; Queue Ops)</option>
          <option value="ADMINISTRATOR">ADMINISTRATOR (Full Access &amp; Approvals)</option>
        </select>
      </div>
      <div>
        <label class="b-label" for="stf-pass">Initial Password (min 8 chars)</label>
        <input id="stf-pass" type="password" name="password" class="b-input" placeholder="••••••••" minlength="8" required>
      </div>
      <div>
        <button type="submit" class="b-btn b-btn--primary b-btn--sm" style="width: 100%;">Create Staff User</button>
      </div>
    </form>
  </div>

  <div class="b-table-wrap">
    <table class="b-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>Role (D-45)</th>
          <th>Status</th>
          <th>Last Login</th>
          <th>Password Reset &amp; Status Control</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($staffUsers as $su): ?>
          <tr>
            <td class="b-tabular">#<?= (int) $su['id'] ?></td>
            <td><strong><?= View::e($su['full_name']) ?></strong></td>
            <td style="font-family: var(--font-mono); font-size: var(--text-xs);"><?= View::e($su['email']) ?></td>
            <td>
              <span class="b-badge <?= ($su['role'] ?? '') === 'ADMINISTRATOR' ? 'b-badge--blue' : 'b-badge--orange' ?>">
                <?= View::e($su['role']) ?>
              </span>
            </td>
            <td>
              <span class="b-badge <?= !empty($su['is_active']) ? 'b-badge--verified' : 'b-badge--orange' ?>">
                <?= !empty($su['is_active']) ? 'ACTIVE' : 'SUSPENDED' ?>
              </span>
            </td>
            <td class="b-tabular" style="font-size: var(--text-xs);"><?= View::e($su['last_login_at'] ?? 'Never') ?></td>
            <td>
              <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                <form action="/staff/users/<?= (int) $su['id'] ?>/password" method="POST" style="display: inline-flex; gap: 0.25rem; align-items: center;">
                  <?= Csrf::field() ?>
                  <input type="password" name="new_password" class="b-input" placeholder="New password" minlength="8" required style="width: 120px; min-height: 28px; padding: 0.15rem 0.4rem; font-size: 0.7rem;">
                  <button type="submit" class="b-btn b-btn--outline b-btn--sm" style="padding: 0.2rem 0.5rem; font-size: 0.7rem;">Reset</button>
                </form>

                <?php if (!empty($su['totp_enabled'])): ?>
                  <form action="/staff/users/<?= (int) $su['id'] ?>/2fa-reset" method="POST" style="display:inline;" data-confirm="Reset this staff member’s authenticator enrollment?">
                    <?= Csrf::field() ?>
                    <button type="submit" class="b-btn b-btn--outline b-btn--sm" style="padding:.2rem .5rem;font-size:.7rem;">Reset 2FA</button>
                  </form>
                <?php endif; ?>
                <form action="/staff/users/<?= (int) $su['id'] ?>/toggle" method="POST" style="display: inline;">
                  <?= Csrf::field() ?>
                  <button type="submit" class="b-btn b-btn--outline b-btn--sm" style="padding: 0.2rem 0.5rem; font-size: 0.7rem;">
                    <?= !empty($su['is_active']) ? 'Suspend' : 'Activate' ?>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
