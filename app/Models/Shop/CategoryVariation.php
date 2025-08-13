<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
//use Spatie\Translatable\HasTranslations;
class CategoryVariation extends Model
{
   // use HasTranslations;
    protected $table = 'category_variation';
    protected $fillable = ['category_id', 'variation_id'];
   // public $translatable = ['name']; // добавь свои переводимые поля
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class, 'variation_id');
    }
    public static function getVariationsFromManyCategories(array $categoryIds): \Illuminate\Support\Collection
    {
        $allCategoryIds = collect();

        $categories = \App\Models\Shop\ProductCategory::with('parent')->findMany($categoryIds);
        foreach ($categories as $cat) {
            $allCategoryIds->push($cat->id);
            $allCategoryIds = $allCategoryIds->merge($cat->getAllParents()->pluck('id'));
        }

        $allCategoryIds = $allCategoryIds->unique()->values();

        $variationIds = \App\Models\Shop\CategoryVariation::whereIn('category_id', $allCategoryIds)
            ->pluck('variation_id')
            ->unique();

        return \App\Models\Shop\Variation::whereIn('id', $variationIds)->get();
    }

}
