<?php

namespace App\Models\Shop;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoyaltyRule extends Model
{
    use HasFactory;

    protected $table = 'bs_loyalty_rules';

    protected $fillable = [
        'valid_from',
        'earn_percent',
        'earn_expire_days',
        'welcome_bonus_amount',
        'welcome_bonus_expire_days',
        'min_order_sum_for_earn',
        'is_enabled',
    ];

    protected $casts = [
        'valid_from'                => 'date',
        'earn_percent'              => 'integer',
        'earn_expire_days'          => 'integer',
        'welcome_bonus_amount'      => 'integer',
        'welcome_bonus_expire_days' => 'integer',
        'min_order_sum_for_earn'    => 'decimal:2',
        'is_enabled'                => 'boolean',
    ];

    /**
     * Scope: правила, действующие на указанную дату (valid_from <= date).
     */
    public function scopeForDate(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query
            ->whereDate('valid_from', '<=', $date->format('Y-m-d'))
            ->orderByDesc('valid_from');
    }

    /**
     * Найти актуальное правило для даты (например, даты оплаты заказа).
     */
    public static function findForDate(\DateTimeInterface $date): ?self
    {
        return static::forDate($date)->first();
    }

    /**
     * Удобный хелпер: актуальное правило "на сейчас".
     */
    public static function current(): ?self
    {
        return static::findForDate(CarbonImmutable::now());
    }
}
