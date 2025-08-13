<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\Models\Activity as ActivityLog; // <-- ВАЖНО
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\SoftDeletes;
class OrderItem extends Model
{
    use HasFactory;
    use LogsActivity;
    /**
     * @var string
     */
    protected $table = 'shop_order_items';
    protected $fillable = [
        'product_id',
        'qty',
        'unit_price',
        'shop_order_id',
        // добавь сюда все нужные поля, которые массово заполняются
    ];
    // Связи
    public function order()   { return $this->belongsTo(Order::class, 'shop_order_id'); }
    public function product() { return $this->belongsTo(Product::class, 'product_id'); }


    /**
     * Добавляем контекст к каждому событию Spatie:
     * - какой заказ
     * - какая позиция
     * - какой товар (id, имя, sku)
     * - нормализованное название действия
     */
    public function tapActivity(ActivityLog $activity, string $eventName): void
    {
        // безопасно получаем товар по id
        $query = Product::query();

        // если у продукта есть SoftDeletes — добавим withTrashed()
        if (in_array(SoftDeletes::class, class_uses_recursive(Product::class))) {
            $query->withTrashed();
        }

        $product = $query->find($this->product_id);

        $activity->properties = $activity->properties->merge([
            'action'        => match ($eventName) {
                'created' => 'item_created',
                'updated' => 'item_updated',
                'deleted' => 'item_deleted',
                default   => $eventName,
            },
            'order_id'      => $this->shop_order_id,
            'order_item_id' => $this->id,
            'product'       => [
                'id'   => $this->product_id,
                'name' => $this->productDisplayName($product),
                'sku'  => $product?->sku,
            ],
        ]);
    }

    protected function productDisplayName(?Product $product): ?string
    {
        if (! $product) {
            return null;
        }

        // Если название хранится как JSON по локалям (например, в поле 'title')
        $raw = $product->getRawOriginal('title'); // вернёт строку JSON или null
        if ($raw) {
            $arr = is_array($raw) ? $raw : json_decode($raw, true);
            if (is_array($arr)) {
                $locale = config('app.locale');
                // если используешь настройку по умолчанию — подставь её
                $defaultLocale = \App\Models\Setting::value('default_language_code') ?: $locale;

                $name = $arr[$defaultLocale] ?? $arr[$locale] ?? reset($arr);
                if (!empty($name)) {
                    return $name;
                }
            }
        }

        // Фолбэки на обычные поля
        return $product->name ?? $product->title ?? $product->slug ?? null;
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('order.items')
            ->logOnly(['qty', 'unit_price', 'product_id']) // что писать в old/attributes
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }


    public function modifiers()
    {
        return $this->hasMany(ProductItemModifier::class);
    }
}
