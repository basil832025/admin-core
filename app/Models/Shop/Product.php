<?php

namespace App\Models\Shop;

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
    // Характеристики (влияющие и нет — через pivot)
    public function characteristics()
    {
        return $this->belongsToMany(Characteristic::class)
            ->withPivot(['affects_price', 'price_modifier', 'modifier_type']); // +10 грн / -5%
    }
    public function characteristicValues()
    {
        return $this->belongsToMany(CharacteristicValue::class, 'product_characteristic_value')
            ->withPivot(['characteristic_id','price_modifier','product_id', 'value_text', 'value_number', 'value_datetime','characteristic_value_id']);
    }
    public function productVariations()
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function variations()
    {
        return $this->belongsToMany(Variation::class, 'product_variation')
            ->withPivot('price')
            ->withTimestamps();
    }
    // Изображения
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }


    // тут будет обработка доп полей после сохранения, картинки характеристики и вариации
    public function syncFromFormState(array $data): void
    {
        // 🔄 Обновим изображения
        $this->images()->delete();

        foreach ($data['images'] ?? [] as $image) {
            $path = is_string($image)
                ? $image
                : $image->store('products', 'public');

            $this->images()->create([
                'path' => $path,
            ]);
        }

        // 🔄 Обновим вариации
        $this->productVariations()->delete();

        foreach ($data['variation_flags'] ?? [] as $variationId => $enabled) {
            if ($enabled) {
                $this->productVariations()->create([
                    'variation_id' => $variationId,
                    'price' => $data['variation_prices'][$variationId] ?? null,
                ]);
            }
        }

        // 🔄 Обновим характеристики
        \App\Models\Shop\ProductCharacteristicValue::where('product_id', $this->id)->delete();

        foreach ($data['characteristics'] ?? [] as $characteristicId => $value) {
            $char = \App\Models\Shop\Characteristic::find($characteristicId);
            if (! $char) continue;

            $values = is_array($value) ? $value : [$value];

            foreach ($values as $valKey => $valData) {
                $entry = [
                    'product_id' => $this->id,
                    'characteristic_id' => $characteristicId,
                ];

                $price = $data['characteristics_price'][$characteristicId][$valKey] ?? 0;

                switch ($char->field_type) {
                    case 'checkbox':
                        if (is_bool($valData)) {
                            if ($valData) {
                                $entry['characteristic_value_id'] = (int) $valKey;
                            }
                        } else {
                            $entry['characteristic_value_id'] = (int) $valData;
                        }
                        $entry['price_modifier'] = $price;
                        break;

                    case 'multiselect':
                    case 'radio':
                    case 'select':
                        $entry['characteristic_value_id'] = (int) $valData;
                        $entry['price_modifier'] = $price;
                        break;

                    case 'text':
                    case 'textarea':
                    case 'color':
                    case 'file':
                        $entry['value_text'] = $valData;
                        break;

                    case 'number':
                    case 'decimal':
                        $entry['value_number'] = is_numeric($valData) ? (float) $valData : null;
                        break;

                    case 'datetime':
                        $entry['value_datetime'] = \Carbon\Carbon::parse($valData);
                        break;

                    default:
                        $entry['value_text'] = $valData;
                        break;
                }

                if (
                    array_key_exists('characteristic_value_id', $entry)
                    && (
                        ! $entry['characteristic_value_id']
                        || ! \App\Models\Shop\CharacteristicValue::where('id', $entry['characteristic_value_id'])->exists()
                    )
                ) {
                    continue;
                }

                \App\Models\Shop\ProductCharacteristicValue::updateOrCreate(
                    [
                        'product_id' => $entry['product_id'],
                        'characteristic_id' => $entry['characteristic_id'],
                        'characteristic_value_id' => $entry['characteristic_value_id'] ?? null,
                    ],
                    [
                        'value_text' => $entry['value_text'] ?? null,
                        'value_number' => $entry['value_number'] ?? null,
                        'value_datetime' => $entry['value_datetime'] ?? null,
                        'price_modifier' => $entry['price_modifier'] ?? null,
                    ]
                );
            }
        }
    }

}
