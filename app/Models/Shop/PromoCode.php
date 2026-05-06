<?php
// app/Models/Shop/PromoCode.php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

class PromoCode extends Model
{
    use SoftDeletes;

    protected $table = 'bs_shop_promo_codes';

    protected $fillable = [
        'code',
        'percent',
        'is_active',
        'starts_at',
        'ends_at',
        'max_uses',
        'per_client_limit',
        'note',
    ];

    protected $casts = [
        'percent'          => 'decimal:2',
        'is_active'        => 'boolean',
        'starts_at'        => 'datetime',
        'ends_at'          => 'datetime',
        'max_uses'         => 'integer',
        'per_client_limit' => 'integer',
    ];

    /* ------------ Mutators / Helpers ------------ */
    /**
     * Считаем сумму промокода как % от суммы ТОЛЬКО подходящих позиций.
     * Возвращаем ОТРИЦАТЕЛЬНОЕ число.
     */
    /* ----------- расчёт суммы промокода ----------- */

    /**
     * % от суммы ТОЛЬКО подходящих позиций.
     * Возвращает ОТРИЦАТЕЛЬНОЕ число.
     */
    public function calculateAmountForOrder(Order $order): float
    {
        $percent = (float) ($this->percent ?? 0);
        if ($percent <= 0) return 0.0;

        // квалифицированный pluck, чтобы не было "id is ambiguous"
        $ids = function ($rel) {
            $tbl = $rel->getRelated()->getTable();
            return $rel->select("$tbl.id")->pluck("$tbl.id")->map(fn($v)=>(int)$v)->all();
        };

        $productIds   = $ids($this->products());
        $categoryIds  = $ids($this->categories());
        $valueIdsReq  = $ids($this->characteristicValues()); // characteristic_value_id
        $charIdsReq   = $ids($this->characteristics());      // characteristic_id

        $filtersOn = (bool) ($productIds || $categoryIds || $valueIdsReq || $charIdsReq);

        // подгружаем, что есть (attributeValues может отсутствовать)
        $order->loadMissing([
            'items.product.parent',
            'items.product.categories',
            'items.product.mainCategory',
            'items.product.parent.categories',
            'items.product.parent.mainCategory',
        ]);
        if (method_exists(Product::class, 'attributeValues')) {
            $order->loadMissing(['items.product.attributeValues']);
        }

        $eligible = 0.0;

        foreach ($order->items as $row) {
            $product = $row->product;

            if (! $product) continue;

            if ($product->excludedFromPromotions()) {
                continue;
            }

            $matches = ! $filtersOn; // без фильтров — подходит всё

            // ТОВАР
            if (!$matches && $productIds && in_array((int)$product->id, $productIds, true)) {
                $matches = true;
            }

            // КАТЕГОРИИ
            if (!$matches && $categoryIds) {
                $prodCatIds = $this->resolveProductCategoryIds($product);
                if (array_intersect($categoryIds, $prodCatIds)) $matches = true;
            }

            // ХАРАКТЕРИСТИКИ / ЗНАЧЕНИЯ
            if (!$matches && ($valueIdsReq || $charIdsReq)) {
                $prodValueIds = [];
                $prodCharIds  = [];

                if (method_exists($product, 'attributeValues')) {

                    $vals = $product->attributeValues ?? collect();
                  //  dump($vals,$matches,$valueIdsReq,$charIdsReq,$product);
                    $prodValueIds = $vals->pluck('id')->map(fn($v)=>(int)$v)->all();
                    $prodCharIds  = $vals->pluck('pivot.attribute_id')->filter()->map(fn($v)=>(int)$v)->all();
                    if (!$prodCharIds && $vals->first()?->attribute_id) {
                        $prodCharIds = $vals->pluck('attribute_id')->map(fn($v)=>(int)$v)->all();
                    }
                }

                // мета строки: берём только те id, которые входят в разрешённые множества
                [$metaValueIds, $metaCharIds] = $this->extractMetaIdsStrict($row, $valueIdsReq, $charIdsReq);

                $allValueIds = array_values(array_unique(array_merge($prodValueIds, $metaValueIds)));
                $allCharIds  = array_values(array_unique(array_merge($prodCharIds,  $metaCharIds)));

                if ($valueIdsReq && array_intersect($valueIdsReq, $allValueIds)) $matches = true;
                if (!$matches && $charIdsReq && array_intersect($charIdsReq, $allCharIds)) $matches = true;
            }

            if ($matches) {
                $eligible += (float)$row->unit_price * (int)$row->qty;
            }
        }

        if ($eligible <= 0) return 0.0;

        return -round($eligible * ($percent / 100), 2);
    }


    // Удобный метод учёта
    public function markUsed(?int $clientId, ?int $orderId): void
    {
        if (!$orderId) return; // без заказа не считаем

        $this->usages()->firstOrCreate(
            ['order_id' => $orderId],                   // уникальность по order_id
            ['client_id' => $clientId, 'used_at' => now()]
        );
    }

    public function unmarkUsed(?int $orderId): void
    {
        if ($orderId) $this->usages()->where('order_id', $orderId)->delete();
    }
    protected function extractMetaIdsStrict($row, array $allowedValueIds, array $allowedCharIds): array
    {
        $meta = data_get($row, 'meta');

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            if (json_last_error() === JSON_ERROR_NONE) $meta = $decoded;
        }
        if (!is_array($meta)) return [[], []];

        $vals = [];
        $chars = [];

        // 1) явные ключи, которые мы обсуждали и ты используешь
        foreach (Arr::wrap(data_get($meta, 'characteristic_value_ids', [])) as $id) {
            $id = (int)$id; if ($id && in_array($id, $allowedValueIds, true)) $vals[] = $id;
        }
        foreach (Arr::wrap(data_get($meta, 'attribute_value_ids', [])) as $id) {
            $id = (int)$id; if ($id && in_array($id, $allowedValueIds, true)) $vals[] = $id;
        }
        foreach (Arr::wrap(data_get($meta, 'characteristic_ids', [])) as $id) {
            $id = (int)$id; if ($id && in_array($id, $allowedCharIds, true)) $chars[] = $id;
        }
        foreach (Arr::wrap(data_get($meta, 'attribute_ids', [])) as $id) {
            $id = (int)$id; if ($id && in_array($id, $allowedCharIds, true)) $chars[] = $id;
        }

        // 2) options[*] с value_id / characteristic_value_id
        foreach (Arr::wrap(data_get($meta, 'options', [])) as $opt) {
            $vid = (int)($opt['value_id'] ?? $opt['characteristic_value_id'] ?? 0);
            if ($vid && in_array($vid, $allowedValueIds, true)) $vals[] = $vid;

            $aid = (int)($opt['attribute_id'] ?? $opt['characteristic_id'] ?? 0);
            if ($aid && in_array($aid, $allowedCharIds, true)) $chars[] = $aid;
        }

        // 3) shortcut для «розмір» и похожих
        $candidates = [
            data_get($meta, 'size_id'),
            data_get($meta, 'size.value_id'),
            data_get($meta, 'size.id'),
        ];
        foreach ($candidates as $v) {
            $id = (int)$v; if ($id && in_array($id, $allowedValueIds, true)) $vals[] = $id;
        }

        // 4) generic-фоллбек: берём все числа из meta, но оставляем только те, что входят в allowed-наборы
        foreach (Arr::flatten($meta) as $v) {
            if (!is_numeric($v)) continue;
            $n = (int)$v;
            if ($n && in_array($n, $allowedValueIds, true)) $vals[] = $n;
            if ($n && in_array($n, $allowedCharIds, true))  $chars[] = $n;
        }

        return [array_values(array_unique($vals)), array_values(array_unique($chars))];
    }
    /** Извлекает массив int из конкретных путей meta (поддерживает wildcard). */
    protected function idsFromMeta($row, array $paths): array
    {
        foreach ($paths as $path) {
            $raw = data_get($row, $path);
            if (is_null($raw)) continue;

            $arr = Arr::wrap($raw);
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) $arr = $decoded;
            }
            $flat = Arr::flatten($arr);
            $ids  = array_values(array_filter(array_map('intval', $flat), fn($v)=>$v>0));
            if ($ids) return $ids;
        }
        return [];
    }

    /**
     * Generic-фоллбек: собирает ВСЕ числовые ID из meta.
     * Нужен на случай, если ключи у тебя другие (например, meta.props.size.value_id и т.п.).
     */
    protected function idsFromMetaGeneric($row): array
    {
        $raw = data_get($row, 'meta');
        if (is_null($raw)) return [];

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) $raw = $decoded;
        }

        $flat = Arr::flatten(Arr::wrap($raw));
        return array_values(array_filter(array_map('intval', $flat), fn($v)=>$v>0));
    }
    protected function resolveProductCategoryIds(?Product $product): array
    {
        if (! $product) return [];

        $ids = [];

        foreach ([$product, $product->parent] as $candidate) {
            if (! $candidate) continue;

            if ($candidate->category_id) {
                $ids[] = (int) $candidate->category_id;
            }

            $relationIds = $candidate->categories?->pluck('id')->map(fn($v)=>(int)$v)->all() ?? [];
            $ids = array_merge($ids, $relationIds);

            $mainCategoryId = (int) ($candidate->mainCategory?->id ?? 0);
            if ($mainCategoryId > 0) {
                $ids[] = $mainCategoryId;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
    protected function promoMatchesItem($item): bool
    {
        $product   = $item->product ?? null;
        $filtersOn = false;
        $matched   = false;

        // --- ТОВАРЫ
        $productIds = array_filter(array_map('intval', (array) ($this->product_ids ?? [])));
        if ($productIds) {
            $filtersOn = true;
            if ($product && in_array((int) $product->id, $productIds, true)) {
                $matched = true;
            }
        }

        // --- КАТЕГОРИИ
        $categoryIds = array_filter(array_map('intval', (array) ($this->category_ids ?? [])));
        if ($categoryIds) {
            $filtersOn = true;
            $prodCatIds = $product ? $this->resolveProductCategoryIds($product) : [];
            if (array_intersect($categoryIds, $prodCatIds)) {
                $matched = true;
            }
        }

        // --- ХАРАКТЕРИСТИКИ / ЗНАЧЕНИЯ
        $attrIds      = array_filter(array_map('intval', (array) ($this->attribute_ids ?? $this->characteristic_ids ?? [])));
        $attrValueIds = array_filter(array_map('intval', (array) ($this->attribute_value_ids ?? $this->characteristic_value_ids ?? [])));
        if ($attrIds || $attrValueIds) {
            $filtersOn = true;

            // 1) из товара — только если есть такое отношение
            $prodValueIds = [];
            $prodAttrIds  = [];
            if ($product && method_exists($product, 'attributeValues')) {
                $values = $product->attributeValues ?? collect();
                $prodValueIds = $values->pluck('id')->map(fn($v)=>(int)$v)->all();
                // пробуем pivot.attribute_id, если его нет — прямое поле attribute_id
                $prodAttrIds  = $values->pluck('pivot.attribute_id')->filter()->map(fn($v)=>(int)$v)->all();
                if (!$prodAttrIds && $values->first()?->attribute_id) {
                    $prodAttrIds = $values->pluck('attribute_id')->map(fn($v)=>(int)$v)->all();
                }
            }

            // 2) из меты строки заказа (варианты/опции)
            $itemValueIds = array_map('intval', (array) data_get($item, 'meta.attribute_value_ids',
                data_get($item, 'meta.characteristic_value_ids', [])));
            $itemAttrIds  = array_map('intval', (array) data_get($item, 'meta.attribute_ids',
                data_get($item, 'meta.characteristic_ids', [])));

            $allValueIds = array_values(array_unique(array_merge($prodValueIds, $itemValueIds)));
            $allAttrIds  = array_values(array_unique(array_merge($prodAttrIds,  $itemAttrIds)));

            if ($attrValueIds && array_intersect($attrValueIds, $allValueIds)) {
                $matched = true;
            }
            if (!$attrValueIds && $attrIds && array_intersect($attrIds, $allAttrIds)) {
                $matched = true;
            }
        }

        // если фильтров нет — подходит всё; если есть — достаточно совпадения любого (OR)
        return $filtersOn ? $matched : true;
    }
    // Храним код в UPPERCASE без пробелов по краям
    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = mb_strtoupper(trim((string) $value));
    }
    public function characteristicValues()
    {
        return $this->belongsToMany(
            CharacteristicValue::class,
            'bs_shop_promo_code_characteristic_values',
            'promo_code_id',
            'characteristic_value_id'
        )->withPivot('characteristic_id')->withTimestamps();
    }
    public function getDisplayLabelAttribute(): string
    {
        $p = number_format((float) $this->percent, 2, '.', '');
        return "{$this->code} (−{$p}%)";
    }

    /* ------------ Relations ------------ */

    public function categories()
    {
        return $this->belongsToMany(
            ProductCategory::class,
            'bs_shop_promo_code_categories',
            'promo_code_id',
            'category_id'
        )->withTimestamps();
    }

    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'bs_shop_promo_code_products',
            'promo_code_id',
            'product_id'
        )->withTimestamps();
    }

    public function characteristics()
    {
        return $this->belongsToMany(
            Characteristic::class,
            'bs_shop_promo_code_characteristics',
            'promo_code_id',
            'characteristic_id'
        )->withTimestamps();
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class, 'promo_code_id');
    }

    /* ------------ Scopes & Business Rules ------------ */

    public function scopeActive(Builder $q): Builder
    {
        $now = now();
        return $q->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    /** Сколько раз этот код уже использован (всего). */
    public function totalUsages(): int
    {
        return (int) $this->usages()->count();
    }

    /** Сколько раз этот код использовал конкретный клиент. */
    public function clientUsages(int $clientId): int
    {
        return (int) $this->usages()->where('client_id', $clientId)->count();
    }

    /** Остаток глобального лимита (null = без лимита). */
    public function remainingGlobalUses(): ?int
    {
        if ($this->max_uses === null) return null;
        return max(0, $this->max_uses - $this->totalUsages());
    }

    /** Можно ли применить промокод этому клиенту (без учёта области действия по товарам). */
    public function canApplyForClient(?int $clientId): bool
    {
        if (!$this->is_active) return false;

        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) return false;
        if ($this->ends_at && $now->gt($this->ends_at)) return false;

        // глобальный лимит
        $rem = $this->remainingGlobalUses();
        if ($rem !== null && $rem <= 0) return false;

        // лимит на клиента
        if ($clientId !== null) {
            if ($this->clientUsages($clientId) >= $this->per_client_limit) {
                return false;
            }
        }

        return true;
    }
}
