<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Product extends Model
{
    use HasTranslations;

    protected $fillable = [
        'title','sku', 'slug', 'description', 'price', 'old_price',
        'quantity', 'in_stock',
        'seo_title', 'seo_description', 'seo_keywords'
    ];
    protected $casts = [
        'title' => 'array',
        'parent_id' => 'int',
        'description' => 'array',
        'is_visible' => 'boolean',
    ];
    public $translatable = [
        'title',
        'description',
        'seo_title',
        'seo_description',
        'seo_keywords',
    ];
    // App\Models\Product.php

    public function categories()
    {
        return $this->belongsToMany(ProductCategory::class, 'product_product_category', 'product_id', 'product_category_id');

    }

    public function resolvedCategoryCharacteristics()
    {
        return $this->category
            ? $this->category->getAllCharacteristicsWithInheritance()
            : collect();
    }

    public function characteristicValues()
    {
        return $this->belongsToMany(CharacteristicValue::class, 'product_characteristic_value')
            ->withPivot(['characteristic_id','price_modifier','product_id', 'value_text', 'value_number', 'value_datetime','characteristic_value_id']);
    }

    // Изображения
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    // Характеристики (влияющие и нет — через pivot)
    public function characteristics()
    {
        return $this->belongsToMany(Characteristic::class)
            ->withPivot(['affects_price', 'price_modifier', 'modifier_type']); // +10 грн / -5%
    }
}
