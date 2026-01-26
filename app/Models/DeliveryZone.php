<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $table = 'bs_delivery_zones';

    protected $fillable = [
        'name',
        'description',
        'color',
        'delivery_price',
        'delivery_time_min',
        'delivery_time_max',
        'free_delivery_from',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'delivery_price' => 'decimal:2',
        'free_delivery_from' => 'decimal:2',
        'delivery_time_min' => 'integer',
        'delivery_time_max' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
