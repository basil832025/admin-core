<?php
// app/Models/Shop/PromoCodeUsage.php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;

class PromoCodeUsage extends Model
{
    public $timestamps = false; // у нас своё поле used_at

    protected $table = 'shop_promo_code_usages';

    protected $fillable = [
        'promo_code_id',
        'client_id',
        'order_id',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }
}
