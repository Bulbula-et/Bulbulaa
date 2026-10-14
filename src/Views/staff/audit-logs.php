<?php
declare(strict_types=1);
use Bulbula\Core\View;

/**
 * C-29 Administrator-Only Append-Only Audit Logs View.
 */
$canView = !empty($canView);
$logs    = $logs ?? [];
?>
<?php if (!$canView): ?>
  <div class="b-alert b-alert--error">
    <span>C-29 Security Boundary: Audit logs are restricted to the ADMINISTRATOR role. Use the sidebar switcher to preview as Administrator.</span>
  </div>
<?php else: ?>
  <div class="b-table-wrap">
    <table class="b-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Timestamp</th>
          <th>Actor</th>
          <th>Action Code</th>
          <th>Entity</th>
          <th>State Change (Scrubbed of Secrets/PII)</th>
          <th>Reason / Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td class="b-tabular">#<?= (int) $log['id'] ?></td>
            <td class="b-tabular" style="font-size: var(--text-xs);"><?= View::e($log['created_at']) ?></td>
            <td>
              <strong><?= View::e($log['staff_name'] ?? $log['actor_type']) ?></strong>
              <?php if (!empty($log['staff_role'])): ?>
                <div style="font-size: var(--text-xs); color: var(--neutral-600);"><?= View::e($log['staff_role']) ?></div>
              <?php endif; ?>
            </td>
            <td><span class="b-badge b-badge--blue"><?= View::e($log['action_code']) ?></span></td>
            <td class="b-tabular"><?= View::e($log['entity_type']) ?> #<?= (int) $log['entity_id'] ?></td>
            <td style="font-family: var(--font-mono); font-size: 0.7rem; max-width: 22rem; word-break: break-all;">
              <?= View::e($log['after_state_json'] ?? '') ?>
            </td>
            <td style="font-size: var(--text-xs);"><?= View::e($log['reason_notes'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
