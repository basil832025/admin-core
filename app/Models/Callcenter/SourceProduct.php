<?php

namespace App\Models\Callcenter;

use App\Models\Shop\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceProduct extends Model
{
    protected $table = 'bs_cc_source_products';

    protected $fillable = [
        'source_id',
        'external_id',
        'external_parent_id',
        'external_category_id',
        'alias',
        'title',
        'size_label',
        'price',
        'local_product_id',
        'payload',
        'synced_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function localProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'local_product_id');
    }
}
