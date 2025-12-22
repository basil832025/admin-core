<?php

namespace App\Support\Traits;

use App\Models\Shop\Client;
use App\Models\Shop\Product;
use App\Models\Shop\Characteristic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait HasCatalogFilters
{
    protected function getFilterCharacteristics(): Collection
    {
        $rootIds = Product::query()
            ->active()
            ->MainProduct()
            ->pluck('id');

        if ($rootIds->isEmpty()) {
            return collect();
        }

        $childIds = Product::query()
            ->active()
            ->whereIn('parent_id', $rootIds)
            ->pluck('id');

        $productIds = $rootIds->merge($childIds)->unique();

        $valueCounts = DB::table('bs_product_characteristic_value')
            ->whereIn('product_id', $productIds)
            ->groupBy('characteristic_value_id')
            ->select('characteristic_value_id', DB::raw('COUNT(DISTINCT product_id) as cnt'))
            ->pluck('cnt', 'characteristic_value_id');

        $slugs = ['miaso', 'moreprodukti', 'sir', 'sousi', 'ovoci'];

        $characteristics = Characteristic::query()
            ->whereIn('slug', $slugs)
            ->with('values')
            ->get()
            ->keyBy('slug');

        foreach ($characteristics as $char) {
            $char->setRelation(
                'values',
                $char->values->filter(function ($val) use ($valueCounts) {
                    $cnt = (int) ($valueCounts[$val->id] ?? 0);
                    $val->products_count = $cnt;
                    return $cnt > 0;
                })->values()
            );
        }

        return $characteristics;
    }

    protected function getPriceBounds(string $slug = 'all'): array
    {
        $rootIds = Product::query()
            ->active()
            ->MainProduct()
            ->pluck('id');

        if ($rootIds->isEmpty()) {
            return [0, 0];
        }

        $row = Product::query()
            ->where(function (Builder $q) use ($rootIds) {
                $q->whereIn('id', $rootIds)
                    ->orWhereIn('parent_id', $rootIds);
            })
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $min = (int) floor($row->min_price ?? 0);
        $max = (int) ceil($row->max_price ?? 0);

        if ($min === 0 && $max === 0) {
            return [0, 0];
        }

        if ($min === $max) {
            $min = max(0, $min - 10);
            $max = $max + 10;
        }

        return [$min, $max];
    }

    protected function applyFilters(Builder $q, Request $request): void
    {
        // ----- меню -----
        $menuSlugs = collect($request->input('menu', []))
            ->filter()
            ->unique()
            ->values();

        if ($menuSlugs->isNotEmpty()) {
            $q->where(function (Builder $qq) use ($menuSlugs) {
                $qq->whereHas('categories', function (Builder $qqq) use ($menuSlugs) {
                    $qqq->whereIn('slug', $menuSlugs);
                })
                    ->orWhereHas('mainCategory', function (Builder $qqq) use ($menuSlugs) {
                        $qqq->whereIn('slug', $menuSlugs);
                    });
            });
        }

        // ----- цена -----
        $priceMin = $request->input('price_min');
        $priceMax = $request->input('price_max');

        if ($priceMin !== null && $priceMin !== '') {
            $q->where('price', '>=', (float) $priceMin);
        }

        if ($priceMax !== null && $priceMax !== '') {
            $q->where('price', '<=', (float) $priceMax);
        }

        // ----- характеристики -----
        $charsInput = $request->input('chars', []);
        $selectedValueIds = collect($charsInput)
            ->flatten()
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        if ($selectedValueIds->isNotEmpty()) {
            $productIds = DB::table('bs_product_characteristic_value')
                ->whereIn('characteristic_value_id', $selectedValueIds)
                ->pluck('product_id')
                ->unique();

            if ($productIds->isEmpty()) {
                $q->whereRaw('1 = 0');
            } else {
                $rootIds = Product::query()
                    ->whereIn('id', $productIds)
                    ->selectRaw('CASE WHEN parent_id IS NULL THEN id ELSE parent_id END AS root_id')
                    ->pluck('root_id')
                    ->unique();

                if ($rootIds->isEmpty()) {
                    $q->whereRaw('1 = 0');
                } else {
                    $q->whereIn('id', $rootIds);
                }
            }
        }
    }

    protected function favoriteIds(): array
    {
        if ($client = $this->currentClient()) {
            return DB::table('bs_favorites')
                ->where('client_id', $client->id)
                ->pluck('product_id')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values()
                ->all();
        }

        return collect((array) session('favorites', []))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function currentClient(): ?Client
    {
        $u = auth('client')->user();
        if ($u instanceof Client) return $u;

        $u = auth()->user();
        return $u instanceof Client ? $u : null;
    }
}
