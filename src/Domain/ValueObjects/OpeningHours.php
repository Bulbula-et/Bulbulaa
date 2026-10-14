<?php
declare(strict_types=1);

namespace Bulbula\Domain\ValueObjects;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Branch Opening Hours & Live Open Status Evaluator (C-09, Gate D-04).
 *
 * V1 Product Rule (Page 5):
 * - Opening hours belong to the Branch.
 * - Show an open / closed status ONLY when the recorded data supports it.
 * - When hours are unknown, say so clearly rather than guessing.
 */
final class OpeningHours
{
    public const STATUS_KNOWN          = 'KNOWN';
    public const STATUS_UNKNOWN        = 'UNKNOWN';
    public const STATUS_BY_APPOINTMENT = 'BY_APPOINTMENT';

    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public function __construct(
        private readonly string $hoursStatus,
        private readonly array $weeklySchedule = []
    ) {}

    public static function fromBranchRow(array $branch): self
    {
        $status = (string) ($branch['opening_hours_status'] ?? self::STATUS_UNKNOWN);
        $rawJson = $branch['opening_hours_json'] ?? null;
        $schedule = [];

        if (is_string($rawJson) && $rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded)) {
                $schedule = $decoded;
            }
        } elseif (is_array($rawJson)) {
            $schedule = $rawJson;
        }

        return new self($status, $schedule);
    }

    /**
     * Evaluates current open/closed state in Addis Ababa local time.
     *
     * @return array{state: 'OPEN'|'CLOSED'|'BY_APPOINTMENT'|'UNKNOWN', badge_label: string, detail_label: string, css_modifier: string}
     */
    public function evaluateNow(?DateTimeImmutable $now = null): array
    {
        if ($this->hoursStatus === self::STATUS_BY_APPOINTMENT) {
            return [
                'state'        => 'BY_APPOINTMENT',
                'badge_label'  => 'By Appointment',
                'detail_label' => 'Call branch to schedule a visit',
                'css_modifier' => 'unknown',
            ];
        }

        if ($this->hoursStatus !== self::STATUS_KNOWN || empty($this->weeklySchedule)) {
            return [
                'state'        => 'UNKNOWN',
                'badge_label'  => 'Hours Not Recorded',
                'detail_label' => 'Opening hours have not been confirmed yet',
                'css_modifier' => 'unknown',
            ];
        }

        $tz  = new DateTimeZone('Africa/Addis_Ababa');
        $now = $now ? $now->setTimezone($tz) : new DateTimeImmutable('now', $tz);

        $dayKey = strtolower($now->format('D')); // mon..sun
        $todaySlot = $this->weeklySchedule[$dayKey] ?? null;

        if (!is_array($todaySlot) || !empty($todaySlot['closed'])) {
            return [
                'state'        => 'CLOSED',
                'badge_label'  => 'Closed Today',
                'detail_label' => 'Closed on ' . $now->format('l'),
                'css_modifier' => 'closed',
            ];
        }

        $openStr  = (string) ($todaySlot['open'] ?? '');
        $closeStr = (string) ($todaySlot['close'] ?? '');

        if (!preg_match('/^\d{2}:\d{2}$/', $openStr) || !preg_match('/^\d{2}:\d{2}$/', $closeStr)) {
            return [
                'state'        => 'UNKNOWN',
                'badge_label'  => 'Hours Not Recorded',
                'detail_label' => 'Opening hours have not been confirmed yet',
                'css_modifier' => 'unknown',
            ];
        }

        $currentHm = $now->format('H:i');
        $isOpen    = ($currentHm >= $openStr && $currentHm < $closeStr);

        if ($isOpen) {
            return [
                'state'        => 'OPEN',
                'badge_label'  => 'Open Now',
                'detail_label' => sprintf('Open today %s – %s', $openStr, $closeStr),
                'css_modifier' => 'open',
            ];
        }

        return [
            'state'        => 'CLOSED',
            'badge_label'  => 'Closed Now',
            'detail_label' => sprintf('Today\'s hours: %s – %s', $openStr, $closeStr),
            'css_modifier' => 'closed',
        ];
    }

    /**
     * Returns structured 7-day table for Branch Profile display (C-09).
     */
    public function formattedWeeklyTable(): array
    {
        $labels = [
            'mon' => 'Monday',
            'tue' => 'Tuesday',
            'wed' => 'Wednesday',
            'thu' => 'Thursday',
            'fri' => 'Friday',
            'sat' => 'Saturday',
            'sun' => 'Sunday',
        ];

        $rows = [];
        foreach (self::DAYS as $key) {
            $slot = $this->weeklySchedule[$key] ?? null;
            if ($this->hoursStatus !== self::STATUS_KNOWN || !is_array($slot)) {
                $rows[$key] = ['day' => $labels[$key], 'hours' => 'Not recorded'];
            } elseif (!empty($slot['closed'])) {
                $rows[$key] = ['day' => $labels[$key], 'hours' => 'Closed'];
            } else {
                $rows[$key] = [
                    'day'   => $labels[$key],
                    'hours' => sprintf('%s – %s', $slot['open'] ?? '—', $slot['close'] ?? '—'),
                ];
            }
        }
        return $rows;
    }
}
