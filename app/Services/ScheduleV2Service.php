<?php

namespace App\Services;

use App\Models\Location;
use Carbon\Carbon;

class ScheduleV2Service
{
    public function isEnabled(?Location $location): bool
    {
        return (bool) ($location?->schedule_v2_enabled ?? false) && is_array($location?->schedule_v2);
    }

    public function buildPayload(?Location $location, ?Carbon $now = null, int $days = 14): array
    {
        $now ??= now('Europe/Kyiv');

        if (! $this->isEnabled($location)) {
            return [
                'enabled' => false,
                'timezone' => 'Europe/Kyiv',
                'now' => $now->toIso8601String(),
                'methods' => [],
            ];
        }

        return [
            'enabled' => true,
            'timezone' => 'Europe/Kyiv',
            'now' => $now->toIso8601String(),
            'methods' => [
                'pickup' => $this->buildMethodPayload($location, 'pickup', $now, $days),
                'delivery' => $this->buildMethodPayload($location, 'delivery', $now, $days),
            ],
        ];
    }

    public function buildMethodPayload(Location $location, string $method, Carbon $now, int $days = 14): array
    {
        $dates = [];
        $slotsByDate = [];
        $asapAvailable = $this->isAsapAvailable($location, $method, $now);

        for ($i = 0; $i < $days; $i++) {
            $date = $now->copy()->startOfDay()->addDays($i);
            if (! $this->isDateAvailable($location, $method, $date, $now)) {
                continue;
            }

            $dateKey = $date->toDateString();
            $slots = $this->buildSlotsForDate($location, $method, $date, $now);
            if (count($slots) === 0) {
                continue;
            }

            $dates[] = $dateKey;
            $slotsByDate[$dateKey] = $slots;
        }

        return [
            'asap_available' => $asapAvailable,
            'available_dates' => $dates,
            'next_available_date' => $dates[0] ?? null,
            'slots_by_date' => $slotsByDate,
        ];
    }

    public function isAsapAvailable(Location $location, string $method, Carbon $now): bool
    {
        $cfg = $this->resolveDayConfig($location, $method, $now);
        if (! $cfg || ! $cfg['is_working']) {
            return false;
        }

        if ($this->isClosedDate($location, $method, $now->toDateString())) {
            return false;
        }

        $open = $this->toMinutes($cfg['open_time']);
        $cutoff = $this->toMinutes($cfg['today_cutoff_time']);
        $nowM = ((int) $now->format('H')) * 60 + ((int) $now->format('i'));

        return $nowM >= $open && $nowM <= $cutoff;
    }

    public function isDateAvailable(Location $location, string $method, Carbon $date, Carbon $now): bool
    {
        $cfg = $this->resolveDayConfig($location, $method, $date);
        if (! $cfg || ! $cfg['is_working']) {
            return false;
        }

        $dateKey = $date->toDateString();
        if ($this->isClosedDate($location, $method, $dateKey)) {
            return false;
        }

        if ($date->isSameDay($now)) {
            $cutoff = $this->toMinutes($cfg['today_cutoff_time']);
            $nowM = ((int) $now->format('H')) * 60 + ((int) $now->format('i'));
            return $nowM <= $cutoff;
        }

        return true;
    }

    public function buildSlotsForDate(Location $location, string $method, Carbon $date, Carbon $now): array
    {
        $cfg = $this->resolveDayConfig($location, $method, $date);
        if (! $cfg || ! $cfg['is_working']) {
            return [];
        }

        $open = $this->toMinutes($cfg['open_time']);
        $close = $this->toMinutes($cfg['close_time']);
        $step = max(5, (int) ($cfg['interval_step_minutes'] ?? 15));

        if ($close <= $open) {
            return [];
        }

        $start = $open;
        if ($date->isSameDay($now)) {
            $nowM = ((int) $now->format('H')) * 60 + ((int) $now->format('i'));
            $start = max($start, $this->ceilToStep($nowM, $step));
        }

        $slots = [];
        for ($cur = $start; $cur < $close; $cur += $step) {
            $next = min($cur + $step, $close);
            if ($next <= $cur) {
                continue;
            }
            $slots[] = $this->toTime($cur) . '-' . $this->toTime($next);
        }

        return $slots;
    }

    private function resolveDayConfig(Location $location, string $method, Carbon $date): ?array
    {
        $v2 = (array) ($location->schedule_v2 ?? []);
        $methodData = (array) ($v2[$method] ?? []);
        $daysRaw = (array) ($methodData['days'] ?? []);
        $days = $this->normalizeDays($daysRaw);
        $key = strtolower($date->format('D')); // mon..sun

        $cfg = (array) ($days[$key] ?? []);
        if (count($cfg) === 0) {
            return null;
        }

        return [
            'is_working' => (bool) ($cfg['is_working'] ?? false),
            'open_time' => (string) ($cfg['open_time'] ?? '09:00'),
            'close_time' => (string) ($cfg['close_time'] ?? '21:00'),
            'today_cutoff_time' => (string) ($cfg['today_cutoff_time'] ?? '20:00'),
            'interval_step_minutes' => (int) ($cfg['interval_step_minutes'] ?? 15),
        ];
    }

    /**
     * Supports both map format (days.mon) and repeater list format ([{day_key: mon, ...}]).
     *
     * @param array<int|string, mixed> $daysRaw
     * @return array<string, array<string, mixed>>
     */
    private function normalizeDays(array $daysRaw): array
    {
        $normalized = [];

        foreach ($daysRaw as $k => $row) {
            if (! is_array($row)) {
                continue;
            }

            $dayKey = is_string($k) && in_array($k, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true)
                ? $k
                : (string) ($row['day_key'] ?? '');

            if (! in_array($dayKey, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true)) {
                continue;
            }

            $normalized[$dayKey] = $row;
        }

        return $normalized;
    }

    private function isClosedDate(Location $location, string $method, string $date): bool
    {
        $v2 = (array) ($location->schedule_v2 ?? []);
        $methodData = (array) ($v2[$method] ?? []);
        $raw = (array) ($methodData['closed_dates'] ?? []);
        $closedDates = [];
        foreach ($raw as $row) {
            if (is_array($row)) {
                $val = $row['date'] ?? null;
                if (is_string($val) && $val !== '') {
                    $closedDates[] = $val;
                }
                continue;
            }

            if (is_string($row) && $row !== '') {
                $closedDates[] = $row;
            }
        }

        return in_array($date, $closedDates, true);
    }

    private function toMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');
        return ((int) $h) * 60 + ((int) $m);
    }

    private function toTime(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%02d:%02d', $h, $m);
    }

    private function ceilToStep(int $minutes, int $step): int
    {
        if ($step <= 0) return $minutes;
        return (int) (ceil($minutes / $step) * $step);
    }
}
