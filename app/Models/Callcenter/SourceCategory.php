<?php

namespace App\Models\Callcenter;

use App\Models\Shop\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceCategory extends Model
{
    protected $table = 'bs_cc_source_categories';

    protected $fillable = [
        'source_id',
        'external_id',
        'external_parent_id',
        'alias',
        'title',
        'local_category_id',
        'payload',
        'synced_at',
    ];

    protected $casts = [
        'title' => 'array',
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function localCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'local_category_id');
    }
}
