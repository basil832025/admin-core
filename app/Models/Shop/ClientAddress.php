<?php

namespace App\Models\Shop;

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
    ];

    protected $casts = [
        'is_private_house' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

}
