<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

class HolidayPeriod extends Model
{
    use HasTranslations;
    use SoftDeletes;

    protected $table = 'bs_shop_holiday_periods';

    protected $fillable = [
        'date_from',
        'date_to',
        'comment',
        'is_active',
    ];

    public $translatable = [
        'comment',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'comment' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForDate(Builder $query, Carbon|string $date): Builder
    {
        $dateKey = $date instanceof Carbon
            ? $date->toDateString()
            : Carbon::parse($date)->toDateString();

        return $query
            ->active()
            ->whereDate('date_from', '<=', $dateKey)
            ->whereDate('date_to', '>=', $dateKey);
    }

    public function scopeIntersecting(Builder $query, Carbon|string $from, Carbon|string $to): Builder
    {
        $fromKey = $from instanceof Carbon ? $from->toDateString() : Carbon::parse($from)->toDateString();
        $toKey = $to instanceof Carbon ? $to->toDateString() : Carbon::parse($to)->toDateString();

        return $query
            ->active()
            ->whereDate('date_from', '<=', $toKey)
            ->whereDate('date_to', '>=', $fromKey);
    }

    public function containsDate(Carbon|string $date): bool
    {
        $dateKey = $date instanceof Carbon
            ? $date->toDateString()
            : Carbon::parse($date)->toDateString();

        return $this->is_active
            && $this->date_from?->toDateString() <= $dateKey
            && $this->date_to?->toDateString() >= $dateKey;
    }

    public function localizedComment(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return trim((string) $this->getTranslation('comment', $locale, false));
    }
}
