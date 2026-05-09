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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;

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
        'description_title',
        'seo_title',
        'seo_description',
        'seo_keywords',
    ];

    protected $casts = [
       // 'title' => 'array',
        'parent_id' => 'int',
     //   'description' => 'array',
        'is_visible' => 'boolean',
    ];
    public $translatable = [
        'title',
        'description',
        'description_title',
        'seo_title',
        'seo_description',
        'seo_keywords',

    ];
    protected $appends = ['name'];

    protected static function booted(): void
    {
        static::deleting(function (ProductCategory $category): void {
            if ($category->hasDeleteDependencies()) {
                throw new \RuntimeException($category->getDeleteDependencyMessage());
            }
        });
    }

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
     public function getMainTabCharacteristicsWithInheritance(): Collection
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

    public function getDescendantIds(): array
    {
        $allIds = [];
        $levelIds = [$this->getKey()];

        while ($levelIds !== []) {
            $childIds = self::query()
                ->whereIn('parent_id', $levelIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($childIds === []) {
                break;
            }

            $allIds = array_merge($allIds, $childIds);
            $levelIds = $childIds;
        }

        return array_values(array_unique($allIds));
    }

    public function getDeleteDependencies(): array
    {
        $dependencies = [];
        $descendantIds = $this->getDescendantIds();

        $directProductsCount = Product::query()->where('category_id', $this->getKey())->count();
        if ($directProductsCount > 0) {
            $dependencies[] = "є {$directProductsCount} товарів у цій категорії";
        }

        $additionalProductsCount = Product::query()
            ->whereHas('categories', fn (Builder $query) => $query->whereKey($this->getKey()))
            ->count();
        if ($additionalProductsCount > 0) {
            $dependencies[] = "є {$additionalProductsCount} товарів у додаткових категоріях";
        }

        if ($descendantIds !== []) {
            $dependencies[] = 'є ' . count($descendantIds) . ' дочірніх категорій';

            $descendantProductsCount = Product::query()
                ->where(function (Builder $query) use ($descendantIds): void {
                    $query->whereIn('category_id', $descendantIds)
                        ->orWhereHas('categories', fn (Builder $categories) => $categories->whereIn('bs_product_categories.id', $descendantIds));
                })
                ->distinct('bs_products.id')
                ->count('bs_products.id');

            if ($descendantProductsCount > 0) {
                $dependencies[] = "є товари у дочірніх категоріях ({$descendantProductsCount})";
            }
        }

        $tableChecks = [
            ['table' => 'bs_category_characteristic', 'column' => 'category_id', 'label' => 'є характеристики категорії'],
            ['table' => 'bs_category_variation', 'column' => 'category_id', 'label' => 'є зв\'язки з варіаціями'],
            ['table' => 'bs_shop_time_discount_categories', 'column' => 'category_id', 'label' => 'категорія використовується у часових знижках'],
            ['table' => 'bs_shop_promo_code_categories', 'column' => 'category_id', 'label' => 'категорія використовується у промокодах'],
            ['table' => 'bs_cc_source_categories', 'column' => 'local_category_id', 'label' => 'категорія використовується у зовнішній синхронізації'],
            ['table' => 'bs_characteristics', 'column' => 'category_id', 'label' => 'є характеристики, прив\'язані напряму'],
        ];

        foreach ($tableChecks as $check) {
            if (! Schema::hasTable($check['table']) || ! Schema::hasColumn($check['table'], $check['column'])) {
                continue;
            }

            if (DB::table($check['table'])->where($check['column'], $this->getKey())->exists()) {
                $dependencies[] = $check['label'];
            }
        }

        return array_values(array_unique($dependencies));
    }

    public function hasDeleteDependencies(): bool
    {
        return $this->getDeleteDependencies() !== [];
    }

    public function getDeleteDependencyMessage(): string
    {
        $dependencies = $this->getDeleteDependencies();

        if ($dependencies === []) {
            return 'Категорію можна видалити.';
        }

        return "Не можна видалити категорію:\n- " . implode("\n- ", $dependencies);
    }

    public static function flatMenu(?int $limit = null): Collection
    {
        $locale = app()->getLocale();

        // Берём все корневые и их потомков (глубоко), только видимые
        $roots = self::query()
            ->where('is_visible', 1)
            ->whereNull('parent_id')
            ->orderBy('order')
            ->with([
                'children' => function ($q) {
                    $q->where('is_visible', 1)
                        ->orderBy('order')
                        ->with(['children' => function ($q) {
                            $q->where('is_visible', 1)->orderBy('order')->with('children');
                        }]);
                },
            ])
            ->get();

        // Рекурсивно «сплющиваем» в одну коллекцию
        $flat = collect();
        $walk = function ($nodes) use (&$walk, &$flat, $locale) {
            foreach ($nodes as $n) {
                $flat->push([
                    'id'    => $n->id,
                    'title' => $n->getTranslation('title', $locale) ?: $n->title, // на текущем языке
                    'slug'  => $n->slug,
                    'url'   =>  $n->slug,
                    'order' => $n->order ?? 0,
                ]);

                if ($n->relationLoaded('children') && $n->children->isNotEmpty()) {
                    $walk($n->children);
                }
            }
        };
        $walk($roots);

        $flat = $flat->unique('id')->sortBy('order')->values();

        return $limit ? $flat->take($limit) : $flat;
    }
}
