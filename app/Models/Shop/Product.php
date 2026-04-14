<?php

namespace App\Models\Shop;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\DB;

class Product extends Model implements HasMedia
{
    use InteractsWithMedia;

    use HasTranslations;
    // Коллекции медиа
    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('images') // имя коллекции
            ->useDisk('public');           // тот же диск, что у поля
    }

    // Миниатюры (для компактного превью)
    public function registerMediaConversions(Media $media = null): void
    {
        $this
            ->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->nonQueued(); // можно убрать, если очередь настроена
    }
    protected $table = 'bs_products';
    protected $fillable = [
        'title','sku', 'slug', 'description', 'price', 'old_price',
        'quantity', 'in_stock','main_image','parent_id','short_name',
        'seo_title', 'seo_description', 'seo_keywords','category_id','dop_info',
          'is_new',  'is_hit',  'is_home', 'is_promo', 'is_vegan', 'is_product_of_day', 'is_spicy', 'code2', 'is_imported', 'import_source_id', 'sort','short_desc', 'main_image_small',
    ];
    protected $casts = [
        'title' => 'array',
        'parent_id' => 'int',
        'description' => 'array',
        'is_visible' => 'boolean',
        'is_new'  => 'boolean',
        'is_hit'  => 'boolean',
        'is_home' => 'boolean',
        'is_promo' => 'boolean',
        'is_vegan' => 'boolean',
        'is_product_of_day' => 'boolean',
        'is_spicy' => 'boolean',
        'is_imported' => 'boolean',
        'import_source_id' => 'int',
        'sort'    => 'integer',
    ];
    public $translatable = [
        'title',
        'description',
        'seo_title',
        'seo_description',
        'seo_keywords',
    ];
    public function calculations()
    {
        return $this->hasMany(\App\Models\Shop\ProductCalculation::class);
    }

    /** Активная на сегодня калькуляция */
    public function currentCalculation(?\DateTimeInterface $date = null): ?\App\Models\Shop\ProductCalculation
    {
        $date = $date ?? now();
        return $this->calculations
            ->first(fn($c) => $c->isActiveOn($date));
    }
    // главная категория
    public function mainCategory()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }
    public function getMainImageUrlAttribute(): ?string
    {
        $path = $this->main_image;
        if (!$path) return null;

        // если уже абсолютный URL — отдаем как есть
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        // если сохраняете на public-диск (как в FileUpload)
        return Storage::disk('public')->url($path);
    }

    // родитель
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
    public function variants(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_id');
    }
    // варианты (дети)
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }
    public function attributeValues(): BelongsToMany
    {
        return $this->characteristicValues();
    }
    // Показывать только «родителей» (для списка)
    public function scopeParents($q)
    {
        return $q->whereNull('parent_id');
    }
    public function isVariant(): bool
    {
        return ! is_null($this->parent_id);
    }
    // Удобный аксессор для названия варианта
    public function getDisplayTitleAttribute(): string
    {
        if (!$this->parent_id) return (string)($this->title ?? '');
        // например: «Пицца Маргарита — 900 г»
        return trim(($this->parent?->title ?? '') . ' — ' . ($this->title ?? $this->sku ?? ''));
    }
    // Генерация уникального slug (если пуст)
    protected static function booted(): void
    {
        static::saving(function ($product) {
            if ($product->parent_id && ! $product->category_id) {
                $product->category_id = Product::where('id', $product->parent_id)->value('category_id');
            }
        });
        static::saving(function (Product $m) {
            if (!filled($m->slug)) {
                $base = Str::slug($m->title ?? ($m->parent?->title.' '.$m->sku) ?? Str::random(6));
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->when($m->exists, fn($q)=>$q->whereKeyNot($m->getKey()))->exists()) {
                    $slug = "{$base}-{$i}";
                    $i++;
                }
                $m->slug = $slug;
            }
        });
    }
/** Характеристики только для главной вкладки */
    public function mainTabCharacteristics()
    {
        // адаптируй под свои связи.
        // Если is_main_tab в таблице characteristics:
        return $this->characteristics()->where('is_main_tab', 1);
        // Если флаг в pivot CategoryCharacteristic — добавь ->wherePivot(...)
    }
    public function scopeVariants($query)
    {
        return $query->whereNotNull('parent_id');
    }
    public function categories()
    {
        return $this->belongsToMany(ProductCategory::class, 'bs_product_product_category', 'product_id', 'product_category_id');

    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'bs_product_ingredient', 'product_id', 'ingredient_id')
            ->withPivot(['sort_order'])
            ->orderByPivot('sort_order')
            ->orderBy('bs_ingredients.id');
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
 /*   public function characteristicValues()
    {
        return $this->belongsToMany(
            \App\Models\Shop\CharacteristicValue::class,
            'product_characteristic_value',   // pivot
            'product_id',                     // FK текущей модели в pivot
            'characteristic_value_id',        // FK связанной модели в pivot
            'id',                             // PK products
            'id'                              // PK characteristic_values
        )
            ->withPivot([
                'characteristic_id',
                'characteristic_value_id',
                'price_modifier',
                'value_text',
                'value_number',
                'value_datetime',
            ])
            ->withTimestamps();
    }*/
    public function characteristicValues()
    {
        return $this->belongsToMany(CharacteristicValue::class, 'bs_product_characteristic_value')
            ->withPivot(['characteristic_id','price_modifier','product_id', 'value_text', 'value_number',
                'value_datetime','characteristic_value_id']);
    }
    public function productCharacteristicValues(): HasMany
    {
        return $this->hasMany(ProductCharacteristicValue::class, 'product_id');
    }
    public function productVariations()
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function variations()
    {
        return $this->belongsToMany(Variation::class, 'bs_product_variation')
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
        ProductCharacteristicValue::where('product_id', $this->id)->delete();
     //   dd('tyt');
        foreach ($data['characteristics'] ?? [] as $characteristicId => $value) {
            $char = Characteristic::find($characteristicId);
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
                        $entry['value_datetime'] = Carbon::parse($valData);
                        break;

                    default:
                        $entry['value_text'] = $valData;
                        break;
                }

                if (
                    array_key_exists('characteristic_value_id', $entry)
                    && (
                        ! $entry['characteristic_value_id']
                        || ! CharacteristicValue::where('id', $entry['characteristic_value_id'])->exists()
                    )
                ) {
                    continue;
                }

                ProductCharacteristicValue::updateOrCreate(
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
    public function getDisplayNameAttribute(): string
    {
        // 1) short_name приоритетно
        if (!empty($this->short_name)) {
            return $this->short_name;
        }

        // 2) title как JSON по локали
        $arr = $this->title; // из-за casts уже массив или null
        if (is_array($arr)) {
            $appLocale     = config('app.locale');
            $defaultLocale = Setting::value('default_language_code') ?: $appLocale;
            $name = $arr[$defaultLocale] ?? $arr[$appLocale] ?? reset($arr);
            if (!empty($name)) {
                return $name;
            }
        }

        // 3) фоллбеки
        if (!empty($this->name)) return (string) $this->name;
        if (is_string($this->title) && $this->title !== '') return $this->title;
        if (!empty($this->slug)) return (string) $this->slug;

        return '—';
    }
    //********************************
    //*** для фронта**********************
    // Название с учётом локали (uk по умолчанию)
    public function displayName(): Attribute
    {
        return Attribute::get(function () {
            $arr = (array) ($this->getRawOriginal('title') ? $this->title : []);
            $locale = app()->getLocale() ?: 'uk';
            return $arr[$locale] ?? $arr['uk'] ?? $arr['ru'] ?? $this->attributes['title'] ?? '—';
        });
    }

    // Короткое имя, если используешь short_name — иначе берём displayName
    public function displayShort(): Attribute
    {
        return Attribute::get(fn () => $this->short_name ?: $this->display_name);
    }

    // URL главной картинки
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
            $path = $attributes['main_image'] ?? null;

            if (empty($path)) {
                return asset('storage/images/placeholder.svg');
            }

            // файлы в storage/app/public/products/main/...
            return asset('storage/' . ltrim($path, '/'));
        },
    );
    }

    // Числовая цена (подставь реальное имя поля)
    public function unitPrice(): Attribute
    {
        $value = $this->price ?? $this->base_price ?? 0;
        return Attribute::get(fn () => (float) $value);
    }

    /* Базовые скоупы */
    public function scopeActive($q)   { return $q->where('in_stock', 1); }
    public function scopeMainProduct($q)   { return $q->whereNull('parent_id'); }
    public function scopeHit($q)      { return $q->where('is_hit', 1); }
    public function scopeNew($q)      { return $q->where('is_new', 1); }
    public function scopeHome($q)     { return $q->where('is_home', 1); }
    public function scopePie($q)
    {
        return $q->whereHas('mainCategory.parent', function ($q) {
            $q->where('slug', 'pies');
        });
    }
    public function scopeCardSelect($q)
    {
        return $q->select([
            'id',
            'title',
            'price',
            'old_price',
            'main_image',
            'slug',
            'code2',
            'description',
            'category_id',
            'seo_title',
            'is_hit',
            'is_promo',
            'is_vegan',
            'is_product_of_day',
            'is_spicy',
        ])
            ->with('categories:id,slug,title');
    }

    public function scopeWithCardRelations($q)
    {
        return $q->with([
            'productCharacteristicValues.characteristic.svgImage',
            'productCharacteristicValues.characteristicValue',
            'ingredients',
            'children' => function ($query) {
                $query->select([
                    'id',
                    'title',
                    'price',
                    'old_price',
                    'main_image',
                    'slug',
                    'code2',
                    'description',
                    'category_id',
                    'seo_title',
                    'parent_id',
                    'sort',
                    'is_hit',
                    'is_promo',
                    'is_vegan',
                    'is_product_of_day',
                    'is_spicy',
                ]);
            },
            'children.productCharacteristicValues.characteristic.svgImage',
            'children.productCharacteristicValues.characteristicValue',
            'mainCategory:id,slug',// ← можно сузить столбцы
        ]);
    }
    public static function getPriceBoundsForCategorySlug(string $slug): array
    {
        // 1. берём ID всех "главных" товаров в этой категории (по твоей логике MainProduct + categories/mainCategory)
        $rootIds = self::query()
            ->active()
            ->MainProduct()
            ->where(function ($q) use ($slug) {
                $q->whereHas('categories', fn ($qq) => $qq->where('slug', $slug))
                    ->orWhereHas('mainCategory', fn ($qq) => $qq->where('slug', $slug));
            })
            ->pluck('id');

        if ($rootIds->isEmpty()) {
            return [0, 0];
        }

        // 2. считаем min/max по самим товарам и их детям
        // если у тебя есть поле promo_price / special_price — сюда лучше поставить COALESCE
        $row = self::query()
            ->where(function ($q) use ($rootIds) {
                $q->whereIn('id', $rootIds)          // сами родительские товары
                ->orWhereIn('parent_id', $rootIds); // все их варианты/дети
            })
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $min = (int) floor($row->min_price ?? 0);
        $max = (int) ceil($row->max_price ?? 0);

        // защита от пустых случаев, когда min == max
        if ($min === 0 && $max === 0) {
            return [0, 0];
        }

        if ($min === $max) {
            // чуть расширим диапазон, чтобы ползунок не слипался
            $min = max(0, $min - 10);
            $max = $max + 10;
        }

        return [$min, $max];
    }
    // App\Models\Shop\Product.php

    public function adminBaseName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        // 1) short_name текущего
        $name = trim((string) ($this->short_name ?? ''));

        // 2) short_name родителя
        if ($name === '' && $this->parent_id) {
            $name = trim((string) ($this->parent?->short_name ?? ''));
        }

        // 3) title текущего (перевод)
        if ($name === '') {
            $t = $this->getTranslation('title', $locale, false);
            $name = trim((string) $t);
        }

        // 4) title родителя
        if ($name === '' && $this->parent_id) {
            $t = $this->parent?->getTranslation('title', $locale, false);
            $name = trim((string) $t);
        }

        return $name;
    }

    /**
     * Размер для скобок.
     * У тебя на скринах это похоже на 23/29/33 (часто это диаметр).
     * Если у тебя размер хранится в sku — берём sku.
     * Если нет — можно заменить на нужное поле (например code2).
     */
    public function adminSizeValue(): ?string
    {
        $val = trim((string) ($this->sku ?? ''));

        // если sku пустой — возможно размер в code2
        if ($val === '' && isset($this->code2)) {
            $val = trim((string) $this->code2);
        }

        // оставляем только похожее на размер (23/29/33)
        if ($val !== '' && preg_match('/^\d{2,3}$/', $val)) {
            return $val;
        }

        return null;
    }

    /**
     * Итоговый лейбл: "Название [29]" или "↳ Название [29]" для дочернего
     */
    public function adminLabel(?string $locale = null, bool $withArrow = true): string
    {
        $base = $this->adminBaseName($locale);
        $size = $this->adminSizeValue();

        $prefix = ($withArrow && $this->parent_id) ? '↳ ' : '';
        $suffix = $size ? " [{$size}]" : '';

        return trim($prefix . $base . $suffix);
    }

}
if (! function_exists('schema')) {
    function schema() { return \Illuminate\Support\Facades\Schema::getFacadeRoot(); }
}
