<?php

namespace App\Models\Shop;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
class ProductItemModifier extends Model
{
    protected $table = 'bs_product_item_modifiers';
    protected $fillable = [
        'order_item_id',
        'type',
        'product_id',
        'value_id',
        'price_modifier',
    ];
    protected static function booted()
    {
        static::created(function (self $m) {
            $m->loadMissing('orderItem.product');     // <— ВАЖНО
            $product = $m->orderItem?->product;

            activity('order.items')
                ->causedBy(auth()->user())
                ->performedOn($m)
                ->event('created')
                ->withProperties([
                    'action'        => 'modifier_created',
                    'order_id'      => $m->orderItem?->shop_order_id,
                    'order_item_id' => $m->order_item_id,
                    'product'       => [
                        'id'   => $product?->id,
                        'name' => self::productName($product),
                    ],
                    'modifier'      => [
                        'id'            => $m->id,
                        'type'          => $m->type,
                        'value_id'      => $m->value_id,
                        'value_label'   => self::modifierLabel($m),
                        'price_modifier'=> $m->price_modifier,
                    ],
                ])->log('Добавлен модификатор');
        });

        static::updated(function (self $m) {
            // Подтягиваем связанные данные, чтобы было имя товара
            $m->loadMissing('orderItem.product');
            $product = $m->orderItem?->product;   // <-- исправили опечатку

            // Какие поля модификатора изменились
            $dirtyKeys = array_keys($m->getDirty());

            // Нас интересуют только эти поля
            $interesting = ['value_id', 'value_label', 'price_modifier'];

            // Оставим пересечение двух наборов
            $changed = array_values(array_intersect($dirtyKeys, $interesting));

            if (empty($changed)) {
                // Ничего из интересующего не поменялось — не пишем лог
                return;
            }

            // Старые значения только по изменённым ключам
            $old = Arr::only($m->getOriginal(), $changed);

            // Новые значения по тем же ключам
            $new = Arr::only($m->getAttributes(), $changed);

            activity('order.items')
                ->causedBy(auth()->user())
                ->performedOn($m)
                ->event('updated')
                ->withProperties([
                    'action'        => 'modifier_updated',
                    'order_id'      => $m->orderItem?->shop_order_id,
                    'order_item_id' => $m->order_item_id,

                    'product' => [
                        'id'   => $product?->id,
                        'name' => self::productName($product),
                    ],

                    'modifier' => [
                        'id'             => $m->id,
                        'type'           => $m->type,
                        'value_id'       => $m->value_id,
                        'value_label'    => self::modifierLabel($m),
                        'price_modifier' => $m->price_modifier,
                    ],

                    'old'        => $old,
                    'attributes' => $new,
                ])
                ->log('Изменён модификатор');
        });

        static::deleted(function (self $m) {
            // ← Явно достаём строку товара с продуктом,
            // независимо от состояния ленивых связей
            $item = OrderItem::with('product')->find($m->order_item_id);
            $product = $item?->product;

            activity('order.items')
                ->causedBy(auth()->user())
                ->performedOn($m)
                ->event('deleted')
                ->withProperties([
                    'action'        => 'modifier_deleted',
                    'order_id'      => $item?->shop_order_id, // безопаснее, чем через связь
                    'order_item_id' => $m->order_item_id,
                    'product'       => [
                        'id'   => $product?->id,
                        'name' => self::productName($product),   // тот же хелпер с fallback по языкам
                    ],
                    'modifier'      => [
                        'id'             => $m->id,
                        'type'           => $m->type,
                        'value_id'       => $m->value_id,
                        'value_label'    => self::modifierLabel($m),
                        'price_modifier' => $m->price_modifier,
                    ],
                    'snapshot' => $m->getOriginal(), // можно оставить для отладки
                ])
                ->log('Удалён модификатор');
        });
    }
    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function item()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('order.items')     // тем же лог-неймом, что и товары
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
    public function product()
    {
        // через OrderItem → Product
        return $this->hasOneThrough(
            Product::class,        // конечная модель
            OrderItem::class,      // промежуточная
            'id',                  // OrderItem.id = ...
            'id',                  // Product.id
            'order_item_id',       // ProductItemModifier.order_item_id → OrderItem.id
            'product_id'           // OrderItem.product_id → Product.id
        );
    }
    // пример: App\Models\Shop\ProductItemModifier

// App\Models\Shop\ProductItemModifier (или твоя модель модификатора)
// App/Models/Shop/ProductItemModifier.php (или общий хелпер/трейt)

    protected static function productName(?Product $p): ?string
    {
        if (! $p) return null;

        $raw = $p->getRawOriginal('title') ?? $p->title;
        $arr = is_string($raw) ? (json_decode($raw, true) ?: []) : (array) $raw;

        // кандидаты языков: дефолт из настроек, текущая локаль приложения и т.д.
        $candidates = array_filter([
            Setting::value('default_language_code') ?? null,
            app()->getLocale(),
            config('app.locale'),
        ]);

        foreach ($candidates as $key) {
            if ($key && !empty($arr[$key])) {
                return (string) $arr[$key];
            }
        }

        // Возьмём первое непустое значение
        foreach ($arr as $val) {
            if (!empty($val)) {
                return (string) $val;
            }
        }

        // Последний запасной вариант
        return $p->name ?: "Товар #{$p->id}";
    }


    protected static function modifierLabel(self $m): ?string
    {
        if ($m->value_label) {
            return $m->value_label; // если уже есть в БД — используем
        }

        if ($m->type === 'variation') {
            $var = ProductVariation::with('variation')->find($m->value_id);
            if ($var) {
                // сделай формат как тебе нравится
                return "{$var->variation->name} • {$var->value} • +" . number_format((float) $m->price_modifier, 2, '.', '');
            }
        }

        if ($m->type === 'characteristic') {
            $cv = CharacteristicValue::with('characteristic')->find($m->value_id);
            if ($cv) {
                return "{$cv->characteristic->name} • {$cv->value}";
            }
        }

        return null;
    }
// App/Models/Shop/ProductItemModifier.php
    // App/Models/Shop/ProductItemModifier.php





    protected static function props(self $mod): array
    {
        // подгрузим товар и заказ
        $item   = $mod->item()->with('order', 'product')->first();
        $order  = $item?->order;
        $product = $item?->product;

        // человеко-понятное имя значения модификатора
        $valueLabel = null;

        if ($mod->type === 'variation') {
            $pv = ProductVariation::with('variation')->find($mod->value_id);
            // Например: "Размер • 29/900/3 (+125.00)"
            $valueLabel = $pv?->variation?->name;
            if ($pv?->price) {
                $valueLabel = trim(($valueLabel ? $valueLabel . ' • ' : '') . '+' . $pv->price);
            }
        } elseif ($mod->type === 'characteristic') {
            $cv = CharacteristicValue::with('characteristic')->find($mod->value_id);
            // Например: "Тесто — тонкое"
            $valueLabel = $cv ? ($cv->characteristic?->name . ' — ' . $cv->value) : null;
        }

        return [
            'order_id'      => $order?->id ?? $item?->shop_order_id ?? null,
            'order_item_id' => $item?->id,
            'product'       => [
                'id'   => $product?->id,
                'name' => $product?->title ?? $product?->name ?? null,
            ],
            'modifier'      => [
                'id'             => $mod->id,
                'type'           => $mod->type,
                'value_id'       => $mod->value_id,
                'value_label'    => $valueLabel,
                'price_modifier' => $mod->price_modifier,
            ],
        ];
    }
}
