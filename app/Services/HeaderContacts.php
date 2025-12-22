<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Setting;

class HeaderContacts
{
    public function buildBySlug(string $slug): array
    {
        $loc = Location::query()
                ->where('is_active', 1)
                ->where('slug', $slug)
                ->first()
            ?? Location::query()->where('is_active', 1)->orderBy('sort')->first();

        return $this->pack($loc);
    }

    public function build(?int $locationId = null): array
    {
        $locationId = $locationId ?? (int) (Setting::value('default_location_id') ?? 0);

        $loc = Location::query()
            ->where('is_active', 1)
            ->when($locationId, fn ($q) => $q->whereKey($locationId))
            ->orderBy('sort')
            ->first();

        return $this->pack($loc);
    }

    private function pack(?Location $loc): array
    {
        $locale ??= app()->getLocale();
        $schedule = collect((array) data_get($loc, 'schedule', []))
            ->filter(fn ($row) => (bool) data_get($row, 'is_active', true))
            ->map(function ($row) use ($locale) {

                $slug = (string) data_get($row, 'slug', '');
              //  $time =  $row->getTranslation('time', $locale);

                $title = data_get($row, "title.$locale.$locale")
                    ?? data_get($row, "title.uk.uk")       // fallback если нет перевода
                    ?? '';
                $time = data_get($row, "time.$locale.$locale")
                    ?? data_get($row, "time.uk.uk")       // fallback если нет перевода
                    ?? '';
                return [
                    'slug'    => $slug,
                     'time'     => $time,
                    'title'    => $title,
                ];
            })->values();
      //  dd($schedule);
        $phones = collect((array) data_get($loc, 'phones', []))
            ->filter(fn ($row) => (bool) data_get($row, 'is_active', true))
            ->map(function ($row) {
                $raw  = trim((string) data_get($row, 'phone', ''));
                $slug = (string) data_get($row, 'slug', '');
                $note = (string) data_get($row, 'note', '');

                $digits = preg_replace('/\D+/', '', $raw);
                $tel = '';
                if ($digits !== '') {
                    if (strlen($digits) === 10 && $digits[0] === '0') {
                        $tel = '+38' . $digits;
                    } elseif (strlen($digits) === 12 && str_starts_with($digits, '380')) {
                        $tel = '+' . $digits;
                    } else {
                        $tel = '+' . ltrim($digits, '+');
                    }
                }

                return [
                    'slug'    => $slug,
                    'display' => $raw,
                    'tel'     => $tel,
                    'note'    => $note,
                ];
            })
            ->filter(fn ($p) => $p['display'] !== '' && $p['tel'] !== '')
            ->values();

        $primarySlug = (string) (Setting::value('phone') ?? '');
        $primary =
            $phones->firstWhere('slug', $primarySlug)
            ?? $phones->first(fn ($p) => in_array(strtolower($p['slug']), ['main','primary','default','osn']))
            ?? $phones->first();

        return ['phones' => $phones, 'primary' => $primary, 'schedule' => $schedule, 'location' => $loc];
    }
}
