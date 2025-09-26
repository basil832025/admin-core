<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;
use App\Models\Setting;
use App\Models\Language;
use SolutionForest\FilamentTree\Concern\ModelTree;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Collection;
class ProductCategory extends Model
{

    use ModelTree;
    use HasTranslations;

   // use TreeModel;
    protected $table = 'bs_product_categories';
     protected $fillable = [
        'title',
        'slug',
        'parent_id',
        'is_visible',
        'order',
        'description',
    ];

    protected $casts = [
       // 'title' => 'array',
        'parent_id' => 'int',
     //   'description' => 'array',
        'is_visible' => 'boolean',
    ];
    public $translatable = [
        'title',
        'description'

    ];
    protected $appends = ['name'];
    public function products()
    {
        return $this->hasMany(\App\Models\Shop\Product::class, 'category_id');
     //   return $this->belongsToMany(Product::class, 'product_product_category', 'product_category_id', 'product_id');
    }
    public function getNameAttribute(): string
    {
        $locale = Setting::value('default_language_code') ?: app()->getLocale();
        return $this->getTranslation('title', $locale) ?? '';
    }
   /* public function getNameAttribute(): string
    {
        $defaultLocale = Setting::value('default_language_code') ?: app()->getLocale();
       // dd($defaultLocale,$this->getTranslation('title', $defaultLocale));
        return  $this->getTranslation('title', $defaultLocale);
    }*/

    // Удобный scope для сортировки по текущей локали (MySQL 8+)
    public function scopeOrderByName($q, string $dir = 'asc')
    {
        $loc = app()->getLocale();
        return $q->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"{$loc}\"')) {$dir}");
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

        $variationIds = CategoryVariation::whereIn('category_id', $allCategoryIds)
            ->pluck('variation_id')
            ->unique();

        return Variation::whereIn('id', $variationIds)->get();
    }


    /** IDs пути: [эта категория, её родитель, ..., корень] */
    public function pathIds(): array
    {
        return collect([$this])
            ->merge($this->getAllParents() ?? collect())
            ->pluck('id')
            ->values()
            ->all();
    }

    /** Вариации с наследованием от этой категории вверх */
    public function getAllVariationsWithInheritance(): Collection
    {
        $categoryIds = $this->pathIds();
        if (empty($categoryIds)) {
            return collect();
        }

        $variationIds = CategoryVariation::query()
            ->whereIn('category_id', $categoryIds)
            ->pluck('variation_id')
            ->unique()
            ->values();

        return Variation::query()
            ->whereIn('id', $variationIds)
            ->get();
    }

    /** Характеристики с наследованием от этой категории вверх */
    public function getAllCharacteristicsWithInheritance(?bool $onlyMainTab = null): Collection
    {
        $categoryIds = $this->pathIds();
        if (empty($categoryIds)) {
            return new Collection(); // пустая коллекция
        }

        $chars = Characteristic::query()
            // фильтр по самой характеристике, НЕ по pivot
            ->when($onlyMainTab !== null, fn ($q) =>
            $q->where('is_main_tab', $onlyMainTab ? 1 : 0)
            )
            // ограничиваем по пути категорий через pivot
            ->whereHas('categories', fn ($q) =>
            $q->whereIn('category_id', $categoryIds)
            )
            // подгружаем категории с их pivot, чтобы потом выбрать «ближайшую»
            ->with(['categories' => fn ($q) =>
            $q->whereIn('bs_product_categories.id', $categoryIds)
            ])
            ->get();
       // dd($chars);
        // приоритет — чем ближе к текущей категории, тем выше
        $priority = array_values($categoryIds);

        $chars->each(function ($char) use ($priority) {
            $pivotCategory = $char->categories
                ->sortBy(fn ($cat) => array_search($cat->id, $priority))
                ->first();

            if ($pivotCategory && $pivotCategory->pivot) {
                // чтобы далее обращаться как $char->pivot
                $char->setRelation('pivot', $pivotCategory->pivot);
            }
        });

        return $chars;
    }
// Удобный алиас
    public function getMainTabCharacteristicsWithInheritance(): EloquentCollection
    {
        return $this->getAllCharacteristicsWithInheritance(true);
    }
    public function getAllParents()
    {
        $parents = collect();
        $category = $this;
        while ($category->parent) {
            $parents->push($category->parent);
            $category = $category->parent;
        }
        return $parents;
    }

    public function characteristics()
    {
        return $this->belongsToMany(
            Characteristic::class,
            'bs_category_characteristic',
            'category_id',
            'characteristic_id'
        )->withPivot(['is_required', 'affects_price']);
    }
    public function variations(): BelongsToMany
    {
        return $this->belongsToMany(Variation::class, 'bs_category_variation', 'category_id', 'variation_id');
    }
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }
 /*   public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }*/

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    // генерация slug  при создании и для текущего языка по умолчанию
  /*  protected static function booted(): void
    {
        static::saving(function (ProductCategory $post) {

            // проверяем, что slug ещё пустой и есть title
            if ($post->slug) {
                return;
            }


            // Берём «сырое» значение JSON
            //   getAttribute('name')
            $raw = $post->getAttributes() ?? '{}';
            //dd($raw);
            // Декодируем в массив
            $names = json_decode($raw['name'], true);
            //  dd($names);
            // выбираем строку из массива по локали
            $locale = $defaultLocale;
            // Берём по ключу локали или первый непустой
            $titleForSlug = Arr::get($names, $locale)
                ?: Arr::first($names, fn($value) => ! empty($value));
            //   dd($titleForSlug);
            if ($titleForSlug) {
                $post->slug = Str::slug($titleForSlug);
            }
        });
    }*/
}
