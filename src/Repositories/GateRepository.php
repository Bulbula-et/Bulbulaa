<?php
declare(strict_types=1);

namespace Bulbula\Repositories;

use Bulbula\Core\Cache;
use Bulbula\Core\Config;
use Bulbula\Core\Database;

/**
 * System Readiness & Decision Gates Repository (Page 14: D-04 to D-45).
 */
final class GateRepository
{
    public function isEnabled(string $gateKey, bool $default = false): bool
    {
        $all = $this->allIndexed();
        if (isset($all[$gateKey])) {
            return (bool) $all[$gateKey]['is_enabled'];
        }
        return (bool) Config::get('gates.' . $gateKey, $default);
    }

    public function allIndexed(): array
    {
        return Cache::remember('gates:all', 600, ['gates'], static function (): array {
            try {
                $rows = Database::fetchAll('SELECT * FROM system_gates ORDER BY decision_ref ASC');
                $indexed = [];
                foreach ($rows as $row) {
                    $indexed[(string) $row['gate_key']] = $row;
                }
                return $indexed;
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public function setGateState(string $gateKey, bool $enabled, int $staffId): void
    {
        Database::execute(
            'UPDATE system_gates SET is_enabled = :enabled, updated_by_staff_id = :staff_id, updated_at = CURRENT_TIMESTAMP WHERE gate_key = :key',
            [
                ':enabled'  => $enabled ? 1 : 0,
                ':staff_id' => $staffId,
                ':key'      => $gateKey,
            ]
        );
        Cache::invalidateTags(['gates', 'campaigns']);
    }
}
