<?php

namespace App\Models\Shop;

use App\Enums\OrderStatus;
use App\Models\Shop\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
class Order extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;
    /**
     * @var string
     */
    protected $table = 'shop_orders';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'number',
        'total_price',
        'status',
        'client_address_id',
        'clients_id',
        'currency',
        'shipping_price',
        'shipping_method',
        'notes',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
    ];

    /** @return MorphOne<OrderAddress> */
  /*  public function address(): MorphOne
    {
        return $this->morphOne(OrderAddress::class, 'addressable');
    }*/


    // Что логировать
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('order')
            ->logFillable()          // или ->logAttributes([...])
            ->logOnlyDirty()         // только изменённые поля
            ->dontSubmitEmptyLogs(); // пустые не пишем
    }

    // Человекочитаемое описание
    public function getDescriptionForEvent(string $event): string
    {
        return match ($event) {
            'created' => 'Создание заказа',
            'updated' => 'Изменение заказа',
            'deleted' => 'Удаление заказа',
            'restored'=> 'Восстановление заказа',
            default   => ucfirst($event).' заказа',
        };
    }
    /** @return BelongsTo<Customer,self> */
    public function clients(): BelongsTo //Один заказ принадлежит одному клиенту.
    {
        return $this->belongsTo(Client::class, 'clients_id');
    }

    /** @return HasMany<OrderItem> */
    public function items(): HasMany //Один заказ (Order) имеет много товаров (OrderItem).
    {
        return $this->hasMany(OrderItem::class, 'shop_order_id');
    }
   /* protected static function booted(): void
    {
        // saved - после сохранения  saving - до сохранения основной модели
        static::saved(function (Order $order) {
            $order->load('items.modifiers');
           //  dd($order->items);
            $order->total_price = $order->items->sum(function ($item) {
                $qty = (float) $item->qty;
                $unitPrice = (float) $item->unit_price;

                $modifiers = $item->modifiers ?? collect();
                $modifierSum = $modifiers->sum(fn ($mod) => (float) $mod->price_modifier);

                return $qty * ($unitPrice + $modifierSum);
            });

            $order->saveQuietly(); // ⚠️ чтобы не зациклить saved()
        });
    }*/
    public function recalculateTotalPrice(): void
    {
        $this->loadMissing('items.modifiers');

        $this->total_price = $this->items->sum(function ($item) {
            $qty = (float) $item->qty;
            $unitPrice = (float) $item->unit_price;
            $modifiers = $item->modifiers ?? collect();
            $modifierSum = $modifiers->sum(fn ($m) => (float) $m['price_modifier'] ?? 0);
            return $qty * ($unitPrice + $modifierSum);
        });

        $this->saveQuietly();
    }
  /*  protected static function booted()
    {
        static::saving(function (Order $order) {
            $order->total_price = $order->items->sum(function ($item) {
                $qty = (float) $item->qty;
                $unitPrice = (float) $item->unit_price;

                $modifiers = $item->modifiers ?? collect();

                $modifierSum = $modifiers->sum(function ($mod) {
                    return (float) $mod->price_modifier;
                });

                return $qty * ($unitPrice + $modifierSum);
            });
        });

    }*/
    /** @return HasMany<Payment> */
   /* public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }*/
}
