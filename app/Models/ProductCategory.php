<?php

namespace App\Models;

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

class ProductCategory extends Model
{

    use ModelTree;
    use HasTranslations;

   // use TreeModel;
    protected $table = 'product_categories';
     protected $fillable = [
        'title',
        'slug',
        'parent_id',
        'is_visible',
        'order',
        'description',
    ];

    protected $casts = [
        'title' => 'array',
        'parent_id' => 'int',
        'description' => 'array',
        'is_visible' => 'boolean',
    ];
    public $translatable = [
        'title',
        'description'

    ];
    public function getAllCharacteristicsWithInheritance(): \Illuminate\Support\Collection
    {
        // Получаем текущую и родительские категории
        $categories = collect([$this])->merge($this->getAllParents());
        $categoryIds = $categories->pluck('id')->all();

        // Загружаем характеристики через отношение categories с pivot
        return \App\Models\Characteristic::whereHas('categories', function ($query) use ($categoryIds) {
            $query->whereIn('category_id', $categoryIds);
        })
            ->with(['categories' => function ($query) use ($categoryIds) {
                $query->whereIn('product_categories.id', $categoryIds);
            }])
            ->get()
            ->each(function ($char) use ($categoryIds) {
                // Ищем первую категорию, для которой есть привязка, и берём её pivot
                $pivotCategory = $char->categories->first(fn($cat) => in_array($cat->id, $categoryIds));

                if ($pivotCategory && $pivotCategory->pivot) {
                    $char->setRelation('pivot', $pivotCategory->pivot);
                }
            });
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
            \App\Models\Characteristic::class,
            'category_characteristic',
            'category_id',
            'characteristic_id'
        )->withPivot(['is_required', 'affects_price']);
    }
    public function variations(): BelongsToMany
    {
        return $this->belongsToMany(Variation::class, 'category_variation', 'category_id', 'variation_id');
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
