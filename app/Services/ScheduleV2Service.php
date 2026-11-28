<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Shop\HolidayPeriod;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Schema;

class ScheduleV2Service
{
    public function isEnabled(?Location $location): bool
    {
        return (bool) ($location?->schedule_v2_enabled ?? false) && is_array($location?->schedule_v2);
    }

    public function scheduleNotice(Location $location, Carbon $now, string $locale): ?array
    {
        $notice = $location->schedule_v2['notice'] ?? [];
        if (config('project.name') !== '3piroga' || ! $this->isEnabled($location) || empty($notice['enabled'])) {
            return null;
        }

        $message = trim((string) ($notice['message'][$locale] ?? ''));
        $message = $message !== '' ? $message : trim((string) ($notice['message']['uk'] ?? ''));
        if ($message === '') {
            return null;
        }

        $weekDays = array_flip(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']);
        $startDay = $weekDays[$notice['start_day'] ?? ''] ?? null;
        $endDay = $weekDays[$notice['end_day'] ?? ''] ?? null;
        $periodStart = $now->toDateString();
        if ($startDay !== null && $endDay !== null && ! empty($notice['start_time']) && ! empty($notice['end_time'])) {
            $start = $startDay * 1440 + $this->toMinutes($notice['start_time']);
            $end = $endDay * 1440 + $this->toMinutes($notice['end_time']);
            $current = ($now->dayOfWeekIso - 1) * 1440 + $now->hour * 60 + $now->minute;
            $active = $start <= $end
                ? $current >= $start && $current < $end
                : $current >= $start || $current < $end;
            $startDate = $now->copy()->startOfWeek(Carbon::MONDAY)->addDays($startDay);
            if ($current < $start) {
                $startDate->subWeek();
            }
            $periodStart = $startDate->toDateString();
        } else {
            $pickup = $this->resolveDayConfig($location, 'pickup', $now);
            $delivery = $this->resolveDayConfig($location, 'delivery', $now);
            $active = $pickup && $delivery
                && (! $pickup['is_working'] || $pickup['preorder_only'])
                && (! $delivery['is_working'] || $delivery['preorder_only']);
        }

        return $active ? [
            'id' => "schedule-{$location->getKey()}-$periodStart",
            'comment' => $message,
            'plain_text' => true,
        ] : null;
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
        return $this->buildMethodPayloadForRange(
            $location,
            $method,
            $now->copy()->startOfDay(),
            $now->copy()->startOfDay()->addDays($days - 1),
            $now,
        );
    }

    public function buildMethodPayloadForRange(Location $location, string $method, Carbon $from, Carbon $to, Carbon $now): array
    {
        $dates = [];
        $slotsByDate = [];
        $asapAvailable = $this->isAsapAvailable($location, $method, $now);
        $today = $now->copy()->startOfDay();

        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            if ($date->lt($today)) {
                continue;
            }

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
            'closed_dates' => $from->lte($to) ? $this->holidayDates($from, $to) : [],
            'next_available_date' => $dates[0] ?? null,
            'slots_by_date' => $slotsByDate,
        ];
    }

    public function isAsapAvailable(Location $location, string $method, Carbon $now): bool
    {
        $cfg = $this->resolveDayConfig($location, $method, $now);
        if (! $cfg || ! $cfg['is_working'] || $cfg['preorder_only']) {
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
        if (! $cfg || (! $cfg['is_working'] && ! $cfg['preorder_only'])) {
            return false;
        }

        $dateKey = $date->toDateString();
        if ($this->isClosedDate($location, $method, $dateKey)) {
            return false;
        }

        if ($cfg['preorder_only']) {
            return $this->canPreorder($location, $method, $date, $now);
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
        if (! $cfg || (! $cfg['is_working'] && ! $cfg['preorder_only'])) {
            return [];
        }

        if ($cfg['preorder_only'] && ! $this->canPreorder($location, $method, $date, $now)) {
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

    private function canPreorder(Location $location, string $method, Carbon $date, Carbon $now): bool
    {
        $targetDay = $date->copy()->startOfDay();
        $bookingDay = $now->copy()->startOfDay();
        if ($targetDay->lte($bookingDay)) {
            return false;
        }

        $endOfWeek = $bookingDay->copy()->endOfWeek(Carbon::SUNDAY);
        if ($bookingDay->isWeekend() && $targetDay->isWeekend() && $targetDay->lte($endOfWeek)) {
            return false;
        }

        $booking = $this->resolveDayConfig($location, $method, $now);
        if (! in_array(strtolower($date->format('D')), $booking['preorder_days'] ?? [], true)) {
            return false;
        }

        if ($bookingDay->isFriday() && $targetDay->isWeekend() && $targetDay->gt($endOfWeek)) {
            return true;
        }

        $cutoff = $booking['preorder_cutoff_time'];
        $minutes = $now->hour * 60 + $now->minute;

        return ! $cutoff || $minutes < $this->toMinutes($cutoff);
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
            'preorder_only' => config('project.name') === '3piroga' && (bool) ($cfg['preorder_only'] ?? false),
            'preorder_days' => (array) ($cfg['preorder_days'] ?? []),
            'preorder_cutoff_time' => $cfg['preorder_cutoff_time'] ?? null,
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
        if ($this->holidaysTableExists() && HolidayPeriod::query()->forDate($date)->exists()) {
            return true;
        }

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

    /**
     * @return array<int, string>
     */
    private function holidayDates(Carbon $from, Carbon $to): array
    {
        if (! $this->holidaysTableExists()) {
            return [];
        }

        $dates = [];

        HolidayPeriod::query()
            ->intersecting($from, $to)
            ->get(['date_from', 'date_to'])
            ->each(function (HolidayPeriod $period) use (&$dates, $from, $to): void {
                $periodStart = $period->date_from?->copy()->startOfDay();
                $periodEnd = $period->date_to?->copy()->startOfDay();

                if (! $periodStart || ! $periodEnd) {
                    return;
                }

                $start = $periodStart->greaterThan($from) ? $periodStart : $from->copy();
                $end = $periodEnd->lessThan($to) ? $periodEnd : $to->copy();

                if ($start->gt($end)) {
                    return;
                }

                foreach (CarbonPeriod::create($start, $end) as $date) {
                    $dates[] = $date->toDateString();
                }
            });

        return array_values(array_unique($dates));
    }

    private function holidaysTableExists(): bool
    {
        static $exists = null;

        return $exists ??= Schema::hasTable((new HolidayPeriod())->getTable());
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
