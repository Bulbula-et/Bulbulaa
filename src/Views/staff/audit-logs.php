<?php
declare(strict_types=1);
use Bulbula\Core\View;

/** C-29 Administrator-Only Append-Only Audit Log. */
$canView = !empty($canView);
$logs = $logs ?? [];
$actors = [];
$actions = [];
foreach ($logs as $entry) {
    $actors[(string) ($entry['staff_name'] ?? $entry['actor_type'] ?? 'System')] = true;
    $actions[(string) ($entry['action_code'] ?? 'UNKNOWN')] = true;
}
$latestAt = $logs !== [] ? (string) ($logs[0]['created_at'] ?? '—') : '—';
$formatState = static function (?string $json): string {
    if ($json === null || trim($json) === '') return 'No state payload recorded';
    $decoded = json_decode($json, true);
    return is_array($decoded) ? (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $json;
};
?>
<?php if (!$canView): ?>
  <section class="b-audit-denied"><strong>Administrator authorization required</strong><p>The security boundary restricts append-only audit records to authenticated Administrators. No audit metadata has been disclosed.</p></section>
<?php else: ?>
  <section class="b-audit-boundary">
    <div class="b-audit-boundary__main"><h2>Append-only security record</h2><p>Administrative and operational changes are retained as immutable accountability events. Sensitive credentials and direct customer identifiers are excluded from state snapshots.</p><div class="b-audit-boundary__controls"><span class="b-audit-boundary__control">Administrator only</span><span class="b-audit-boundary__control">Append-only</span><span class="b-audit-boundary__control">Secrets and PII scrubbed</span><span class="b-audit-boundary__control">Accountable changes</span></div></div>
    <article class="b-audit-stat"><span class="b-audit-stat__label">Recorded events</span><strong class="b-audit-stat__value"><?= count($logs) ?></strong><span class="b-audit-stat__meta">Visible security records</span></article>
    <article class="b-audit-stat"><span class="b-audit-stat__label">Distinct actors</span><strong class="b-audit-stat__value"><?= count($actors) ?></strong><span class="b-audit-stat__meta">Staff and system identities</span></article>
    <article class="b-audit-stat"><span class="b-audit-stat__label">Action types</span><strong class="b-audit-stat__value"><?= count($actions) ?></strong><span class="b-audit-stat__meta">Latest event <?= View::e($latestAt) ?></span></article>
  </section>

  <section class="b-card b-audit-register">
    <header class="b-audit-register__head"><div><h2>Security audit register</h2><p>Chronological actor, action, entity, and scrubbed state-change evidence.</p></div><span class="b-audit-seal">Integrity controls active</span></header>
    <?php if ($logs === []): ?>
      <div class="b-audit-empty"><strong>No audit events recorded</strong>Administrative changes will appear here when accountable actions occur.</div>
    <?php else: ?>
      <div class="b-table-wrap" style="border:0;border-radius:0;box-shadow:none"><table class="b-table b-audit-table"><thead><tr><th>Timestamp</th><th>Actor</th><th>Action</th><th>Entity</th><th>Scrubbed state after change</th><th>Reason and notes</th></tr></thead><tbody>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td><span class="b-audit-time"><?= View::e($log['created_at']) ?></span></td>
            <td class="b-audit-actor"><strong><?= View::e($log['staff_name'] ?? $log['actor_type']) ?></strong><?php if (!empty($log['staff_role'])): ?><span><?= View::e(str_replace('_', ' ', $log['staff_role'])) ?></span><?php endif; ?></td>
            <td><span class="b-badge b-badge--blue"><?= View::e(str_replace('_', ' ', $log['action_code'])) ?></span></td>
            <td><span class="b-audit-entity"><?= View::e($log['entity_type']) ?> #<?= (int) $log['entity_id'] ?></span></td>
            <td><code class="b-audit-state"><?= View::e($formatState($log['after_state_json'] ?? null)) ?></code></td>
            <td><div class="b-audit-notes"><?= View::e($log['reason_notes'] ?? 'No additional notes') ?></div></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>
<?php endif; ?>
