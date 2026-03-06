<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;

class Prro extends Model
{
    protected $table = 'bs_shop_prros';

    protected $fillable = [
        'registered_at',
        'certificate_expires_at',
        'organization_name',
        'prro_number',
        'certificate_path',
        'certificate_base64',
        'key_path',
        'key_base64',
        'key_password',
        'use_for_liqpay',
        'is_active',
    ];

    protected $casts = [
        'registered_at' => 'date',
        'certificate_expires_at' => 'date',
        'use_for_liqpay' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $record): void {
            if (! $record->use_for_liqpay) {
                return;
            }

            self::query()
                ->whereKeyNot($record->id)
                ->where('use_for_liqpay', true)
                ->update(['use_for_liqpay' => false]);
        });
    }
}
