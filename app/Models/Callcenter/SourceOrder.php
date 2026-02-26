<?php

namespace App\Models\Callcenter;

use App\Models\Shop\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceOrder extends Model
{
    protected $table = 'bs_cc_source_orders';

    protected $fillable = [
        'source_id',
        'external_id',
        'local_order_id',
        'sync_status',
        'last_error',
        'payload',
        'synced_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function localOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'local_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SourceOrderItem::class, 'source_order_id');
    }
}
