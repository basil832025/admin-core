<?php

namespace App\Models\Shop;

use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAddress extends Model
{
    protected $table = 'bs_client_addresses';
    protected $fillable = [
        'client_id',
        'city',
        'street',
        'house',
        'apartment',
        'intercom',
        'floor',
        'entrance',
        'note',
        'is_private_house',
        'type',
        'latitude',
        'longitude',
        'street_place_id',
        'formatted_address',
        'delivery_zone_id',
        'zone_resolution_method',
        'zone_resolved_at',
    ];

    protected $casts = [
        'is_private_house' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'delivery_zone_id' => 'integer',
        'zone_resolution_method' => 'string',
        'zone_resolved_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

}
