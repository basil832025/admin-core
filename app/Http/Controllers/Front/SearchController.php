<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use App\Support\Presenters\ProductCardPresenter;
use App\Support\Traits\HasCatalogFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    use HasCatalogFilters;
    /** Страница с результатами */
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $locales = \App\Models\Setting::getActiveLocales();
        $locale = app()->getLocale();
        $favoriteIds = $this->favoriteIds();

        if ($q === '') {
            return view('pages.search.index', [
                'q'          => $q,
                'products'   => collect(),
                'categories' => collect(),
                'favoriteIds' => $favoriteIds,
            ]);
        }

        // экранируем пользовательские % и _
        $needle = '%' . addcslashes(mb_strtolower($q), '%_') . '%';

        // ==== ТОВАРЫ ====
        
        $productsQuery = Product::withCardRelations()
            ->cardSelect()
            ->where('in_stock', true)
            ->whereRaw('COALESCE(parent_id,0)=0')
            ->where(function (Builder $w) {
                $this->applyMainSiteProductFilter($w);
            })
            ->where(function (Builder $w) use ($locales, $needle) {
                $this->applyTitleSlugLikeCI($w, $locales, $needle);
                // Поиск по артикулу (code2)
                $w->orWhereRaw('LOWER(`code2`) LIKE ?', [$needle]);
                // Поиск по характеристикам
                $this->applyCharacteristicSearch($w, $locales, $needle);
            })
            ->orderByDesc('sort')
            ->limit(50);
        
        $productsCollection = $productsQuery->get();
        $products = collect((new ProductCardPresenter($locale))->collection($productsCollection));

        // ==== КАТЕГОРИИ ====
        $categories = ProductCategory::query()
            ->select(['id','slug','title','parent_id'])
            ->where('slug', 'not like', 'src-%-import')
            ->whereHas('products', function (Builder $q): void {
                $q->where('in_stock', true)
                    ->whereRaw('COALESCE(parent_id,0)=0')
                    ->where(function (Builder $w): void {
                        $this->applyMainSiteProductFilter($w);
                    });
            })
            ->where(function (Builder $w) use ($locales, $needle) {
                $this->applyTitleSlugLikeCI($w, $locales, $needle);
            })
            ->limit(20)
            ->get();

        return view('pages.search.index', compact('q','products','categories','favoriteIds'));
    }

    /** AJAX-подсказки для хедера */
    public function suggest(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json(['products' => [], 'categories' => []]);
        }

        $locales = \App\Models\Setting::getActiveLocales();
        $needle  = '%' . addcslashes(mb_strtolower($q), '%_') . '%';

        // товары
        $products = Product::query()
            ->select(['id','slug','title','main_image','category_id'])
            ->with(['mainCategory:id,slug,title'])
            ->where('in_stock', true)
            ->whereRaw('COALESCE(parent_id,0)=0')
            ->where(function (Builder $w) {
                $this->applyMainSiteProductFilter($w);
            })
            ->where(function (Builder $w) use ($locales, $needle) {
                $this->applyTitleSlugLikeCI($w, $locales, $needle);
                // Поиск по артикулу (code2)
                $w->orWhereRaw('LOWER(`code2`) LIKE ?', [$needle]);
                // Поиск по характеристикам
                $this->applyCharacteristicSearch($w, $locales, $needle);
            })
            ->limit(6)
            ->get()
            ->map(function (Product $p) {
                $locale = app()->getLocale();
                return [
                    'id'            => $p->id,
                    'title'         => (string) $p->getTranslation('title', $locale),
                    'slug'          => $p->slug,
                    'image'         => $p->main_image_url ?? asset('images/no-image.svg'),
                    'categorySlug'  => $p->mainCategory?->slug,
                    'categoryTitle' => $p->mainCategory?->getTranslation('title', $locale),
                    'url'           => $p->mainCategory
                        ? route('product.show', ['categorySlug' => $p->mainCategory->slug, 'itemSlug' => $p->slug])
                        : route('product.show.flat', ['itemSlug' => $p->slug]),
                ];
            })
            ->values();

        // категории: прямые совпадения по title/slug
        $categories = ProductCategory::query()
            ->select(['id','slug','title'])
            ->where('slug', 'not like', 'src-%-import')
            ->whereHas('products', function (Builder $q): void {
                $q->where('in_stock', true)
                    ->whereRaw('COALESCE(parent_id,0)=0')
                    ->where(function (Builder $w): void {
                        $this->applyMainSiteProductFilter($w);
                    });
            })
            ->where(function (Builder $w) use ($locales, $needle) {
                $this->applyTitleSlugLikeCI($w, $locales, $needle);
            })
            ->limit(6)
            ->get()
            ->map(function (ProductCategory $c) {
                return [
                    'slug'  => $c->slug,
                    'title' => (string) $c->getTranslation('title', app()->getLocale()),
                    'url'   => route('category.show', ['slug' => $c->slug]),
                ];
            })
            ->values();

        return response()->json([
            'products'   => $products,
            'categories' => $categories,
        ]);
    }

    /**
     * Применяет к билдеру OR-условия вида:
     * LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$."uk"'))) LIKE ?
     * для всех локалей + LOWER(slug) LIKE ?
     */
    private function applyTitleSlugLikeCI(Builder $w, array $locales, string $needle): void
    {
        // JSON локали
        foreach ($locales as $loc) {
            // безопасные биндинги вместо подстановки строки
            $w->orWhereRaw(
                "LOWER(JSON_UNQUOTE(JSON_EXTRACT(`title`, '$.\"$loc\"'))) LIKE ?",
                [$needle]
            );
        }

        // slug
        $w->orWhereRaw('LOWER(`slug`) LIKE ?', [$needle]);
    }

    /**
     * Ограничение на товары только основного сайта (без импортированных).
     */
    private function applyMainSiteProductFilter(Builder $query): void
    {
        $query->where(function (Builder $w): void {
            $w->whereNull('is_imported')
                ->orWhere('is_imported', false);
        });
    }

    /**
     * Поиск по характеристикам товаров
     * Ищет по значениям характеристик (CharacteristicValue.value) на всех языках
     * и по текстовым значениям в pivot таблице (value_text)
     * Учитывает характеристики как у родительского товара, так и у дочерних (вариантов)
     */
    private function applyCharacteristicSearch(Builder $w, array $locales, string $needle): void
    {
        // Поиск по value_text в pivot таблице (характеристики родительского товара)
        $w->orWhereExists(function ($query) use ($needle) {
            $query->select(DB::raw(1))
                ->from('bs_product_characteristic_value')
                ->whereColumn('bs_product_characteristic_value.product_id', 'bs_products.id')
                ->whereRaw('LOWER(bs_product_characteristic_value.value_text) LIKE ?', [$needle]);
        });

        // Поиск по value_text в pivot таблице (характеристики дочерних товаров)
        $w->orWhereExists(function ($query) use ($needle) {
            $query->select(DB::raw(1))
                ->from('bs_products AS child_products')
                ->join('bs_product_characteristic_value', 'child_products.id', '=', 'bs_product_characteristic_value.product_id')
                ->whereColumn('child_products.parent_id', 'bs_products.id')
                ->whereRaw('LOWER(bs_product_characteristic_value.value_text) LIKE ?', [$needle]);
        });

        // Поиск по CharacteristicValue.value (JSON поле) на всех языках (характеристики родительского товара)
        foreach ($locales as $loc) {
            $w->orWhereExists(function ($query) use ($needle, $loc) {
                $query->select(DB::raw(1))
                    ->from('bs_product_characteristic_value')
                    ->join('bs_characteristic_values', 'bs_product_characteristic_value.characteristic_value_id', '=', 'bs_characteristic_values.id')
                    ->whereColumn('bs_product_characteristic_value.product_id', 'bs_products.id')
                    ->whereRaw(
                        "LOWER(JSON_UNQUOTE(JSON_EXTRACT(bs_characteristic_values.value, '$.\"$loc\"'))) LIKE ?",
                        [$needle]
                    );
            });
        }

        // Поиск по CharacteristicValue.value (JSON поле) на всех языках (характеристики дочерних товаров)
        foreach ($locales as $loc) {
            $w->orWhereExists(function ($query) use ($needle, $loc) {
                $query->select(DB::raw(1))
                    ->from('bs_products AS child_products')
                    ->join('bs_product_characteristic_value', 'child_products.id', '=', 'bs_product_characteristic_value.product_id')
                    ->join('bs_characteristic_values', 'bs_product_characteristic_value.characteristic_value_id', '=', 'bs_characteristic_values.id')
                    ->whereColumn('child_products.parent_id', 'bs_products.id')
                    ->whereRaw(
                        "LOWER(JSON_UNQUOTE(JSON_EXTRACT(bs_characteristic_values.value, '$.\"$loc\"'))) LIKE ?",
                        [$needle]
                    );
            });
        }

        // Поиск по названию характеристики (например: "Морепродукти") у родительского товара
        foreach ($locales as $loc) {
            $w->orWhereExists(function ($query) use ($needle, $loc) {
                $query->select(DB::raw(1))
                    ->from('bs_product_characteristic_value')
                    ->join('bs_characteristics', 'bs_product_characteristic_value.characteristic_id', '=', 'bs_characteristics.id')
                    ->whereColumn('bs_product_characteristic_value.product_id', 'bs_products.id')
                    ->whereRaw(
                        "LOWER(CASE WHEN JSON_VALID(bs_characteristics.name) THEN JSON_UNQUOTE(JSON_EXTRACT(bs_characteristics.name, '$.\"$loc\"')) ELSE bs_characteristics.name END) LIKE ?",
                        [$needle]
                    );
            });
        }

        // Поиск по названию характеристики у дочерних товаров
        foreach ($locales as $loc) {
            $w->orWhereExists(function ($query) use ($needle, $loc) {
                $query->select(DB::raw(1))
                    ->from('bs_products AS child_products')
                    ->join('bs_product_characteristic_value', 'child_products.id', '=', 'bs_product_characteristic_value.product_id')
                    ->join('bs_characteristics', 'bs_product_characteristic_value.characteristic_id', '=', 'bs_characteristics.id')
                    ->whereColumn('child_products.parent_id', 'bs_products.id')
                    ->whereRaw(
                        "LOWER(CASE WHEN JSON_VALID(bs_characteristics.name) THEN JSON_UNQUOTE(JSON_EXTRACT(bs_characteristics.name, '$.\"$loc\"')) ELSE bs_characteristics.name END) LIKE ?",
                        [$needle]
                    );
            });
        }

        // Поиск по slug характеристики (например: moreprodukti)
        $w->orWhereExists(function ($query) use ($needle) {
            $query->select(DB::raw(1))
                ->from('bs_product_characteristic_value')
                ->join('bs_characteristics', 'bs_product_characteristic_value.characteristic_id', '=', 'bs_characteristics.id')
                ->whereColumn('bs_product_characteristic_value.product_id', 'bs_products.id')
                ->whereRaw('LOWER(bs_characteristics.slug) LIKE ?', [$needle]);
        });

        $w->orWhereExists(function ($query) use ($needle) {
            $query->select(DB::raw(1))
                ->from('bs_products AS child_products')
                ->join('bs_product_characteristic_value', 'child_products.id', '=', 'bs_product_characteristic_value.product_id')
                ->join('bs_characteristics', 'bs_product_characteristic_value.characteristic_id', '=', 'bs_characteristics.id')
                ->whereColumn('child_products.parent_id', 'bs_products.id')
                ->whereRaw('LOWER(bs_characteristics.slug) LIKE ?', [$needle]);
        });
    }
}
