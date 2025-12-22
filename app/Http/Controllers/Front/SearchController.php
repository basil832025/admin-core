<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /** Страница с результатами */
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $locales = \App\Models\Setting::getActiveLocales();

        if ($q === '') {
            return view('front.search.index', [
                'q'          => $q,
                'products'   => collect(),
                'categories' => collect(),
            ]);
        }

        // экранируем пользовательские % и _
        $needle = '%' . addcslashes(mb_strtolower($q), '%_') . '%';

        // ==== ТОВАРЫ ====
        $products = Product::query()
            ->select(['id','slug','title','main_image','category_id'])
            ->with(['mainCategory:id,slug,title'])
            ->where('in_stock', true)
            ->whereRaw('COALESCE(parent_id,0)=0')
            ->where(function (Builder $w) use ($locales, $needle) {
                $this->applyTitleSlugLikeCI($w, $locales, $needle);
            })
            ->orderByDesc('sort')
            ->limit(50)
            ->get();

        // ==== КАТЕГОРИИ ====
        $categories = ProductCategory::query()
            ->select(['id','slug','title','parent_id'])
            ->where(function (Builder $w) use ($locales, $needle) {
                $this->applyTitleSlugLikeCI($w, $locales, $needle);
            })
            ->limit(20)
            ->get();

        return view('front.search.index', compact('q','products','categories'));
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
            ->where(function (Builder $w) use ($locales, $needle) {
                $this->applyTitleSlugLikeCI($w, $locales, $needle);
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
}
