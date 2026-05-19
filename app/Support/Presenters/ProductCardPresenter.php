<?php
// app/Support/Presenters/ProductCardPresenter.php
namespace App\Support\Presenters;

use App\Models\Shop\Product;
use App\Support\Traits\HasLocalizedLabel;
use Illuminate\Database\Eloquent\Model;

class ProductCardPresenter
{
    use HasLocalizedLabel;

    public function __construct(
        protected string $locale = 'uk',
        protected ?string $fallbackCategorySlug = null, // <— добавили
        protected bool $lightweight = false,
    ) {}

    public function for(Product $p): array
    {
        // Артикул: sku если заполнено, иначе code2
        $pickArticle = function ($prod) {
            $sku = trim((string) ($prod->sku ?? ''));
            if ($sku !== '') return $sku;
            return trim((string) ($prod->code2 ?? ''));
        };

        // META характеристик
        $rows = $p->productCharacteristicValues;

        $pickTr = function (Product $prod, string $field): string {
            if (method_exists($prod, 'getTranslation')) {
                $v = (string) ($prod->getTranslation($field, $this->locale) ?? '');
                if ($v !== '') return $v;

                $v = (string) ($prod->getTranslation($field, 'uk') ?? '');
                if ($v !== '') return $v;
            }
            return trim((string) ($prod->{$field} ?? ''));
        };

        $main = $rows
            ->filter(fn ($r) => $r->characteristic && $r->characteristic->is_active && $r->characteristic->is_main_tab)
            ->groupBy('characteristic_id')
            ->map(function ($group) {
                $char = $group->first()->characteristic;

                return [
                    'id'    => $char->id,
                    'slug'  => $char->slug ?? null,
                    'title' => $this->pickLabel($char, $this->locale),
                    'svg'   => $char->svgImage?->url ?? null,
                    'sort'  => $char->sort ?? 0,
                ];
            })
            ->values()
            ->sortBy('sort')
            ->values()
            ->all();

        $mainIds = array_map(fn ($c) => (int) $c['id'], $main);

        // читаем человекочитаемое значение из pivot-строки
        $readVal = function ($r) {
            if ($r->characteristicValue) {
                $v = $r->characteristicValue;

                if (method_exists($v, 'getTranslation')) {
                    $t = $v->getTranslation('value', $this->locale);
                    if ($t !== null && $t !== '') return $t;
                }

                $arr = is_array($v->value) ? $v->value : json_decode((string) $v->value, true);
                if (is_array($arr)) {
                    return $arr[$this->locale] ?? $arr['uk'] ?? $arr['en'] ?? $arr['ru'] ?? reset($arr);
                }
            }

            if ($r->value_text !== null && $r->value_text !== '') return (string) $r->value_text;
            if ($r->value_number !== null) return (string) $r->value_number;
            if (!empty($r->value_datetime)) return (string) $r->value_datetime;

            return null;
        };

        // карта значений по нужным characteristic_id для конкретного продукта
        $buildCharMap = function (Product $prod) use ($mainIds, $readVal) {
            $map  = [];
            $list = $prod->productCharacteristicValues ?? collect();

            foreach ($mainIds as $cid) {
                $vals = $list->where('characteristic_id', $cid)
                    ->map(fn ($r) => $readVal($r))
                    ->filter()
                    ->values();

                if ($vals->isNotEmpty()) {
                    $map[$cid] = $vals->count() > 1 ? $vals->implode(' / ') : $vals->first();
                }
            }
            return $map;
        };

        // выберем slug категории: mainCategory → первая из categories → fallback из конструктора
        $categorySlug = $p->mainCategory?->slug
            ?? ($p->relationLoaded('categories') ? $p->categories->first()?->slug : null)
            ?? $this->fallbackCategorySlug;

        $allCategorySlugs = $p->relationLoaded('categories')
            ? $p->categories->pluck('slug')->filter()->values()->all()
            : [];

        if ($categorySlug && ! in_array($categorySlug, $allCategorySlugs, true)) {
            array_unshift($allCategorySlugs, $categorySlug);
        }

        // Варианты: родитель + дети
        $variantRows = [[
            'product_id'  => $p->id,
            'slug'        => $p->slug,
            'variant_display_sort' => $p->variant_display_sort,
            'is_root'     => true,
            'price'       => $p->price,
            'old_price'   => $p->old_price,
            'manual_discount_percent' => $p->manual_discount_percent,
            'article'     => $pickArticle($p),
            'is_new'      => (bool) ($p->is_new ?? false),
            'is_hit'      => (bool) ($p->is_hit ?? false),
            'is_promo'    => (bool) ($p->is_promo ?? false),
            'is_vegan'    => (bool) ($p->is_vegan ?? false),
            'is_product_of_day' => (bool) ($p->is_product_of_day ?? false),
            'is_spicy'    => (bool) ($p->is_spicy ?? false),
            'char_values' => $buildCharMap($p),
        ]];

        $sortedChildren = collect($p->children ?? [])
            ->sortBy(fn ($child) => sprintf('%010d-%010d', (int) ($child->sort ?? 0), (int) ($child->id ?? 0)))
            ->values();

        foreach ($sortedChildren as $child) {
            $childOldPrice = $child->old_price;
            
            $variantRows[] = [
                'product_id'  => $child->id,
                'slug'        => $child->slug,
                'sort'        => (int) ($child->sort ?? 0),
                'variant_display_sort' => $child->variant_display_sort,
                'is_root'     => false,
                'price'       => $child->price,
                'old_price'   => $childOldPrice,
                'manual_discount_percent' => $child->manual_discount_percent,
                'article'     => $pickArticle($child),
                // Badges are defined on the root product and must stay visible
                // when switching between variant sizes.
                'is_new'      => (bool) ($p->is_new ?? false),
                'is_hit'      => (bool) ($p->is_hit ?? false),
                'is_promo'    => (bool) ($p->is_promo ?? false),
                'is_vegan'    => (bool) ($p->is_vegan ?? false),
                'is_product_of_day' => (bool) ($p->is_product_of_day ?? false),
                'is_spicy'    => (bool) ($p->is_spicy ?? false),
                'char_values' => $buildCharMap($child),
            ];
        }

        $variantRows = collect($variantRows)
            ->sortBy(function (array $row): string {
                $displaySort = $row['variant_display_sort'] ?? null;

                if ($displaySort !== null && $displaySort !== '') {
                    return '0-' . sprintf('%010d-%010d', (int) $displaySort, (int) ($row['product_id'] ?? 0));
                }

                if (($row['is_root'] ?? false) === true) {
                    return '1-0000000000-' . sprintf('%010d', (int) ($row['product_id'] ?? 0));
                }

                return '2-' . sprintf('%010d-%010d', (int) ($row['sort'] ?? 0), (int) ($row['product_id'] ?? 0));
            })
            ->values()
            ->all();

        // ссылки

        $description = method_exists($p, 'getTranslation')
            ? $p->getTranslation('description', $this->locale)
            : $p->description;

        $ingredientText = collect($p->relationLoaded('ingredients') ? ($p->ingredients ?? collect()) : [])
            ->map(function ($ingredient) {
                if (method_exists($ingredient, 'getTranslation')) {
                    $val = $ingredient->getTranslation('name', $this->locale);
                    if (is_string($val) && trim($val) !== '') {
                        return trim($val);
                    }
                }

                $name = $ingredient->name ?? null;
                if (is_array($name)) {
                    $val = $name[$this->locale] ?? $name['uk'] ?? $name['ru'] ?? $name['en'] ?? reset($name);
                    if (is_string($val) && trim($val) !== '') {
                        return trim($val);
                    }
                }

                return null;
            })
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->values();

        $ingredientsText = $ingredientText->isNotEmpty() ? $ingredientText->implode(', ') : '';

        $cardDescription = $this->lightweight
            ? trim((string) ($p->short_desc ?? ''))
            : ($ingredientsText !== '' ? $ingredientsText : $description);

        if ($this->lightweight && $cardDescription === '') {
            $cardDescription = $ingredientsText !== '' ? $ingredientsText : trim((string) ($description ?? ''));
        }

        $productRouteName = in_array($this->locale, ['ru', 'en'], true)
            ? 'localized.product.show'
            : 'product.show';

        $productRouteParams = [
            'categorySlug' => $categorySlug,
            'itemSlug' => $p->slug,
        ];

        if ($productRouteName === 'localized.product.show') {
            $productRouteParams['locale'] = $this->locale;
        }

        $url = ($categorySlug && $p->slug)
            ? route($productRouteName, $productRouteParams)
            : null;
        $category_url = $categorySlug ? $categorySlug : null;

        return [
            'title'           => method_exists($p, 'getTranslation') ? $p->getTranslation('title', $this->locale) : $p->title,
            'description'     => $description,
            'card_description'=> $cardDescription,
            'ingredients_text'=> $ingredientsText,
            'short_desc'      => $p->short_desc,
            'old_price'       => $p->old_price,
            'price'           => $p->price,
            'manual_discount_percent' => $p->manual_discount_percent,
            'main_image'      => $p->main_image_url,
            'slug'            => $p->slug,
            'article'         => $pickArticle($p),
            'characteristics' => $main,
            'variant_rows'    => $variantRows,
            'root_id'         => $p->id,
            // NEW:
            'category_slug'   => $categorySlug,
            'all_category_slugs' => $allCategorySlugs,
            'url'             => $url,
            'category_url'    => $category_url,
        ];

        if (! $this->lightweight) {
            $result['ingredients_text'] = $ingredientsText;
            $result['seo_title'] = $pickTr($p, 'seo_title');
            $result['seo_description'] = $pickTr($p, 'seo_description');
            $result['seo_keywords'] = $pickTr($p, 'seo_keywords');
        }

        return $result;
    }

    /** Коллекции — одним вызовом */
    public function collection(iterable $products): array
    {
        $out = [];
        foreach ($products as $p) {
            $out[] = $this->for($p);
        }
        return $out;
    }

}
