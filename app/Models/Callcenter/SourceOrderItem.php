<?php

namespace App\Models\Callcenter;

use App\Models\Shop\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceOrderItem extends Model
{
    protected $table = 'bs_cc_source_order_items';

    protected $fillable = [
        'source_order_id',
        'external_item_id',
        'external_product_id',
        'title',
        'qty',
        'unit_price',
        'local_product_id',
        'payload',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'payload' => 'array',
    ];

    public function sourceOrder(): BelongsTo
    {
        return $this->belongsTo(SourceOrder::class, 'source_order_id');
    }

    public function localProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'local_product_id');
    }
}
