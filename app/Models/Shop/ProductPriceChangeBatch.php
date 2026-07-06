<?php

namespace App\Models\Shop;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductPriceChangeBatch extends Model
{
    protected $table = 'bs_product_price_change_batches';

    protected $guarded = [];

    protected $casts = [
        'value' => 'decimal:4',
        'include_variants' => 'boolean',
        'category_ids' => 'array',
        'filters' => 'array',
        'affected_count' => 'integer',
        'rounding_precision' => 'integer',
        'reverted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductPriceChangeItem::class, 'batch_id');
    }
}
