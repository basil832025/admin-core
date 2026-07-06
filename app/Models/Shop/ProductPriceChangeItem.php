<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceChangeItem extends Model
{
    protected $table = 'bs_product_price_change_items';

    protected $guarded = [];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'old_old_price' => 'decimal:2',
        'new_old_price' => 'decimal:2',
        'old_discount_percent' => 'decimal:2',
        'new_discount_percent' => 'decimal:0',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductPriceChangeBatch::class, 'batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
