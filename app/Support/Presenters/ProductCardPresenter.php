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
    ) {}

    public function for(Product $p): array
    {
        // META характеристик
        $rows = $p->productCharacteristicValues;

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

        // Варианты: родитель + дети
        $variantRows = [[
            'product_id'  => $p->id,
            'slug'        => $p->slug,
            'price'       => $p->price,
            'old_price'   => $p->old_price,
            'char_values' => $buildCharMap($p),
        ]];

        foreach ($p->children ?? [] as $child) {
            $childOldPrice = $child->old_price;
            
            $variantRows[] = [
                'product_id'  => $child->id,
                'slug'        => $child->slug,
                'price'       => $child->price,
                'old_price'   => $childOldPrice,
                'char_values' => $buildCharMap($child),
            ];
        }

        // Сортируем варианты по размеру (rozmir-pirogiv) - извлекаем числовое значение размера
        $sizeChar = collect($main)->firstWhere('slug', 'rozmir-pirogiv');
        if ($sizeChar && count($variantRows) > 1) {
            $sizeCharId = $sizeChar['id'];
            usort($variantRows, function ($a, $b) use ($sizeCharId) {
                $sizeA = $this->extractSizeNumber($a['char_values'][$sizeCharId] ?? null);
                $sizeB = $this->extractSizeNumber($b['char_values'][$sizeCharId] ?? null);
                
                // Если не удалось извлечь число, оставляем порядок как есть
                if ($sizeA === null && $sizeB === null) return 0;
                if ($sizeA === null) return 1;
                if ($sizeB === null) return -1;
                
                return $sizeA <=> $sizeB; // Сортировка по возрастанию
            });
        }

        // ссылки

        $url          = ($categorySlug && $p->slug)
            ? route('product.show', ['categorySlug' => $categorySlug, 'itemSlug' => $p->slug])
            : null;
        $category_url = $categorySlug ? $categorySlug : null;

        return [
            'title'           => method_exists($p, 'getTranslation') ? $p->getTranslation('title', $this->locale) : $p->title,
            'description'     => method_exists($p, 'getTranslation') ? $p->getTranslation('description', $this->locale) : $p->description,
            'old_price'       => $p->old_price,
            'price'           => $p->price,
            'main_image'      => $p->main_image_url,
            'slug'            => $p->slug,
            'article'         => $p->code2,
            'seo_title'         => $p->seo_title,
            'characteristics' => $main,
            'variant_rows'    => $variantRows,
            'root_id'         => $p->id,
            // NEW:
            'category_slug'   => $categorySlug,
            'url'             => $url,
            'category_url'    => $category_url,
        ];
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

    /**
     * Извлекает числовое значение размера из строки (например, "19 см" -> 19)
     */
    protected function extractSizeNumber(?string $sizeValue): ?int
    {
        if (!$sizeValue) {
            return null;
        }

        // Удаляем все нецифровые символы и извлекаем первое число
        if (preg_match('/\d+/', (string)$sizeValue, $matches)) {
            return (int)$matches[0];
        }

        return null;
    }
}
