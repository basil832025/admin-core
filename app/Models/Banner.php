<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

class Banner extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasTranslations;

    protected $table = 'bs_banners';

    protected $fillable = [
        'title',
        'subtitle',
        'button_text',
        'image',          // универсальная
        'images',         // JSON с локальными
        'image_mobile',
        'images_mobile',
        'url',
        'target',
        'sort',
        'is_active',
        'starts_at',
        'ends_at',
        'schedule',
    ];

    /** Поля, которые будут храниться как JSON переводы */
    public array $translatable = [
        'title',
        'subtitle',
        'button_text',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'images'    => 'array',
        'images_mobile' => 'array',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'schedule'  => 'array',
    ];

    /**
     * Returns max active schedule priority for given moment, or null if no rule matches.
     *
     * schedule[] item format:
     * - is_active: bool
     * - dow_from: int (1..7, ISO, 1=Mon)
     * - time_from: string (HH:MM)
     * - dow_to: int (1..7, ISO, 1=Mon)
     * - time_to: string (HH:MM)
     * - priority: int
     */
    public function schedulePriorityAt(\Carbon\CarbonInterface $now): ?int
    {
        $rules = is_array($this->schedule) ? $this->schedule : [];
        if (!$rules) return null;

        $isoDow = (int) $now->dayOfWeekIso;
        $tWeek = $this->weekMinute($isoDow, $now->format('H:i'));
        $tDay = $this->dayMinute($now->format('H:i'));
        $prevIsoDow = $isoDow === 1 ? 7 : ($isoDow - 1);

        $best = null;
        foreach ($rules as $r) {
            if (!is_array($r)) continue;
            if (!((bool)($r['is_active'] ?? true))) continue;

            $type = (string) ($r['type'] ?? 'range');
            if ($type === '') $type = 'range';

            $active = false;

            if ($type === 'daily') {
                $days = $r['days'] ?? [];
                if (!is_array($days) || !$days) continue;
                $days = array_values(array_unique(array_map('intval', $days)));
                $days = array_values(array_filter($days, fn ($d) => $d >= 1 && $d <= 7));
                if (!$days) continue;

                $tf = (string)($r['time_from'] ?? '');
                $tt = (string)($r['time_to'] ?? '');
                if (!preg_match('/^\d{2}:\d{2}$/', $tf) || !preg_match('/^\d{2}:\d{2}$/', $tt)) continue;

                $from = $this->dayMinute($tf);
                $to = $this->dayMinute($tt);

                if ($from === $to) {
                    $active = false;
                } elseif ($from < $to) {
                    $active = in_array($isoDow, $days, true) && ($tDay >= $from && $tDay < $to);
                } else {
                    // crosses midnight: active on selected day from 'from' to 23:59,
                    // and on next day from 00:00 to 'to' (so we check prev day for early morning).
                    $active = (
                        (in_array($isoDow, $days, true) && $tDay >= $from)
                        ||
                        (in_array($prevIsoDow, $days, true) && $tDay < $to)
                    );
                }
            } else {
                // range: continuous week interval day+time -> day+time
                $df = (int)($r['dow_from'] ?? 0);
                $dt = (int)($r['dow_to'] ?? 0);
                $tf = (string)($r['time_from'] ?? '');
                $tt = (string)($r['time_to'] ?? '');
                if ($df < 1 || $df > 7 || $dt < 1 || $dt > 7) continue;
                if (!preg_match('/^\d{2}:\d{2}$/', $tf) || !preg_match('/^\d{2}:\d{2}$/', $tt)) continue;

                $start = $this->weekMinute($df, $tf);
                $end = $this->weekMinute($dt, $tt);

                $active = ($start <= $end)
                    ? ($tWeek >= $start && $tWeek < $end)
                    : ($tWeek >= $start || $tWeek < $end);
            }

            if (!$active) continue;
            $p = (int)($r['priority'] ?? 0);
            if ($best === null || $p > $best) $best = $p;
        }

        return $best;
    }

    private function weekMinute(int $isoDow, string $hhmm): int
    {
        $isoDow = max(1, min(7, $isoDow));
        $dayIdx = $isoDow - 1; // 0..6

        [$h, $m] = array_pad(explode(':', $hhmm, 2), 2, '0');
        $h = (int)$h;
        $m = (int)$m;
        if ($h < 0) $h = 0;
        if ($h > 23) $h = 23;
        if ($m < 0) $m = 0;
        if ($m > 59) $m = 59;

        return $dayIdx * 1440 + ($h * 60 + $m);
    }

    private function dayMinute(string $hhmm): int
    {
        [$h, $m] = array_pad(explode(':', $hhmm, 2), 2, '0');
        $h = (int)$h;
        $m = (int)$m;
        if ($h < 0) $h = 0;
        if ($h > 23) $h = 23;
        if ($m < 0) $m = 0;
        if ($m > 59) $m = 59;
        return $h * 60 + $m;
    }

    public static function dowLabel(int $isoDow, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $isoDow = max(1, min(7, $isoDow));

        $uk = [1 => 'понеділка', 2 => 'вівторка', 3 => 'середи', 4 => 'четверга', 5 => "п’ятниці", 6 => 'суботи', 7 => 'неділі'];
        $ru = [1 => 'понедельника', 2 => 'вторника', 3 => 'среды', 4 => 'четверга', 5 => 'пятницы', 6 => 'субботы', 7 => 'воскресенья'];
        $en = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

        if (str_starts_with($locale, 'ru')) return $ru[$isoDow] ?? (string)$isoDow;
        if (str_starts_with($locale, 'en')) return $en[$isoDow] ?? (string)$isoDow;
        return $uk[$isoDow] ?? (string)$isoDow;
    }

    public function scheduleHumanLines(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $rules = is_array($this->schedule) ? $this->schedule : [];
        $out = [];
        foreach ($rules as $r) {
            if (!is_array($r)) continue;
            if (!((bool)($r['is_active'] ?? true))) continue;

            $type = (string) ($r['type'] ?? 'range');
            if ($type === '') $type = 'range';

            if ($type === 'daily') {
                $days = $r['days'] ?? [];
                if (!is_array($days) || !$days) continue;
                $days = array_values(array_unique(array_map('intval', $days)));
                $days = array_values(array_filter($days, fn ($d) => $d >= 1 && $d <= 7));
                if (!$days) continue;

                $tf = (string)($r['time_from'] ?? '');
                $tt = (string)($r['time_to'] ?? '');
                if (!preg_match('/^\d{2}:\d{2}$/', $tf) || !preg_match('/^\d{2}:\d{2}$/', $tt)) continue;

                $dayLabels = array_map(fn ($d) => self::dowLabel((int)$d, $locale), $days);
                $out[] = 'З ' . $tf . ' по ' . $tt . ' (' . implode(', ', $dayLabels) . ')';
                continue;
            }

            $df = (int)($r['dow_from'] ?? 0);
            $dt = (int)($r['dow_to'] ?? 0);
            $tf = (string)($r['time_from'] ?? '');
            $tt = (string)($r['time_to'] ?? '');
            if ($df < 1 || $df > 7 || $dt < 1 || $dt > 7) continue;
            if (!preg_match('/^\d{2}:\d{2}$/', $tf) || !preg_match('/^\d{2}:\d{2}$/', $tt)) continue;

            $out[] = 'З ' . self::dowLabel($df, $locale) . ' ' . $tf . ' по ' . self::dowLabel($dt, $locale) . ' ' . $tt;
        }
        return $out;
    }
    /**
     * Вернуть путь картинки для конкретного языка с fallback на универсальную.
     */

    public function getImageForLocale(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        $images = $this->images ?? [];

        // сначала ищем локализованную
        if (!empty($images[$locale])) {
            return $images[$locale];
        }

        // иначе универсальная
        return $this->image;
    }

    public function getMobileImageForLocale(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        $imagesMobile = $this->images_mobile ?? [];

        if (!empty($imagesMobile[$locale])) {
            return $imagesMobile[$locale];
        }

        if (!empty($this->image_mobile)) {
            return $this->image_mobile;
        }

        return $this->getImageForLocale($locale);
    }

    public function getLocalizedUrl(?string $locale = null): ?string
    {
        $url = trim((string) $this->url);
        if ($url === '') {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://', '//', 'mailto:', 'tel:'])) {
            return $url;
        }

        $locale ??= app()->getLocale();
        $prefix = in_array($locale, ['ru', 'en'], true) ? '/' . $locale : '';
        $normalized = '/' . ltrim($url, '/');

        if ($prefix === '') {
            return $normalized;
        }

        if (preg_match('#^/(ru|en)(/|$)#i', $normalized)) {
            return $normalized;
        }

        return $prefix . $normalized;
    }
}
