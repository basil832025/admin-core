<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class Ingredient extends Model
{
    use HasTranslations;

    protected $table = 'bs_ingredients';

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'legacy_consist_id',
    ];

    protected $casts = [
        'name' => 'array',
        'is_active' => 'boolean',
    ];

    public array $translatable = ['name'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'bs_product_ingredient', 'ingredient_id', 'product_id')
            ->withPivot(['sort_order'])
            ->withTimestamps();
    }
}
