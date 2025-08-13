<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAddress extends Model
{
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
    ];

    protected $casts = [
        'is_private_house' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
