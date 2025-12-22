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
use Illuminate\Support\Carbon;
use App\Enums\PaymentMethodEnum;
use App\Services\LoyaltyService;
use App\Models\Shop\LoyaltyTransaction;
use App\Models\Shop\LiqPayLog;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;
    /**
     * @var string
     */
    protected $table = 'bs_shop_orders';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
      //  'number',
        'total_price',
        'status',
        'client_address_id',
        'extra_reason',
        'clients_id',
        'currency',
        'shipping_price',
        'shipping_method',
        'notes',
        'dat',
        'time_start',
        'time_order',
        'date_order',
        'self_pickup',
        'as_soon_possible',
        'payment',
        'reason_non_payment',
        'sale_prc',
        'sale_sum',
        'total_price_sale',
        'short_name',
    ];

    protected $casts = [
        'payment' => PaymentMethodEnum::class,
        'status'       => OrderStatus::class, // раз ты уже используешь enum
        'status_times' => 'array',
        'dat' => 'date',
        'date_order' => 'date',
        'time_start' => 'datetime:H:i',
        'time_order' => 'datetime:H:i',
        'self_pickup' => 'boolean',
        'as_soon_possible' => 'boolean',
        'sale_prc' => 'decimal:2',
        'sale_sum' => 'decimal:2',
        'total_price_sale' => 'decimal:2',
        'address'     => 'array',

    ];

    /** @return MorphOne<OrderAddress> */
  /*  public function address(): MorphOne
    {
        return $this->morphOne(OrderAddress::class, 'addressable');
    }*/
    // app/Models/Shop/Order.php
    /** Зафиксировать время для статуса в статус-таймлайне (JSON). */
    public function touchStatusTime(OrderStatus|string $status, ?\Carbon\Carbon $at = null, bool $overwrite = true): void
    {
        $status = $status instanceof OrderStatus ? $status->value : (string) $status;

        $times = $this->status_times ?? [];
        if ($overwrite || !isset($times[$status])) {
            $times[$status] = ($at ?? now())->format('Y-m-d H:i:s');
            $this->status_times = $times;
        }
    }
    public function clientOrders(): HasMany
    {
        // все заказы того же клиента (включая текущий — исключим его в менеджере)
        return $this->hasMany(self::class, 'clients_id', 'clients_id');
    }
    /**
     * Бонусные операции, связанные с этим заказом.
     */

    public function paymentLogs()
    {
        return $this->hasMany(\App\Models\Shop\LiqPayLog::class, 'shop_order_id');
    }
    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class, 'order_id');
    }
    public function clientAddress()
    {
        return $this->belongsTo(\App\Models\Shop\ClientAddress::class, 'client_address_id');
    }
    public function kitchenTicket()
    {
        return $this->hasOne(\App\Models\Kitchen\KitchenTicket::class, 'order_id');
    }

    public function adjustments()
    {
        return $this->hasMany(\App\Models\Shop\OrderAdjustment::class, 'shop_order_id');
    }
    public function liqpayLogs(): HasMany
    {
        return $this->hasMany(LiqPayLog::class, 'shop_order_id');
    }

    public function lastLiqpayLog(): HasOne
    {
        // последний лог по этому заказу
        return $this->hasOne(LiqPayLog::class, 'shop_order_id')->latestOfMany();
    }
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
    // когда создаётся заказ — ставим отметку времени для стартового статуса
    protected static function booted(): void
    {
        // при создании — зафиксировать время старта статуса
        static::creating(function (self $order) {
            if ($order->getKey()) {
                $order->setAttribute($order->getKeyName(), null);
            }
            // если статус ещё не выставлен — берём дефолт
            $status = $order->status ?? OrderStatus::New;        // enum-объект
            $order->status_times = [$status->value => now()->toDateTimeString()];
            if (blank($order->dat)) {
                $order->dat = now()->toDateString();
            }
            if (blank($order->time_start)) {
                $order->time_start = now()->format('H:i');
            }
            if ($order->time_start) {
                $start = Carbon::parse($order->time_start);

                // если самовывоз -> +15 мин, иначе доставка -> +60
                $order->time_order = $order->self_pickup
                    ? $start->copy()->addMinutes(15)->format('H:i')
                    : $start->copy()->addMinutes(60)->format('H:i');
            }

            // date_order по умолчанию = dat
            if (! $order->date_order && $order->dat) {
                $order->date_order = $order->dat;
            }
            static::created(function (self $order) {
                // присваиваем номер вида OR-00025 после того как БД дала auto-id
                if (blank($order->number) || $order->number === 'OR-00000') {
                    $order->forceFill([
                        'number' => 'OR-' . str_pad((string) $order->getKey(), 5, '0', STR_PAD_LEFT),
                    ])->saveQuietly();
                }
            });
        });

        // при обновлении — дописывать время смены статуса
        static::updating(function (self $order) {
            if ($order->isDirty('status')) {
                $times  = $order->getOriginal('status_times') ?? [];
                $status = $order->status ?? OrderStatus::New;
                $times[$status->value] = now()->toDateTimeString();
                $order->status_times = $times;
            }
            $originalStatus = $order->getOriginal('status');
            $newStatus      = $order->status;

            // приводим к строкам (на случай enum / строки)
            $oldCode = $originalStatus instanceof OrderStatus ? $originalStatus->value : $originalStatus;
            $newCode = $newStatus      instanceof OrderStatus ? $newStatus->value      : $newStatus;

            // интересует только первый переход В "delivered"
            if ($newCode !== OrderStatus::Delivered->value || $oldCode === OrderStatus::Delivered->value) {
                return;
            }

        // если paid_at ещё пустой — считаем, что оплата произошла сейчас
        if (empty($order->paid_at)) {
            $order->paid_at = now();
            // saveQuietly — без повторного срабатывания событий, чтобы не зациклиться
            $order->saveQuietly();
        }

        /** @var LoyaltyService $loyalty */
        $loyalty = app(LoyaltyService::class);

        // начисление X% от чека
        $loyalty->accrueOnPaidOrder($order);

        // приветственный бонус за первый заказ
        $loyalty->grantWelcomeBonusIfNeeded($order);
        });
        static::saving(function (self $order) {
            // базовая сумма из items (если связь подгружена)
            $order->loadMissing('items.modifiers');

            $base = $order->items->sum(function ($item) {
                $qty  = (float) $item->qty;
                $price = (float) $item->unit_price;
                $mods  = collect($item->modifiers ?? []);
                $modsSum = $mods->sum(fn ($m) => (float) ($m['price_modifier'] ?? 0));
                return $qty * ($price + $modsSum);
            });

            $salePrc = max(0, min(100, (float) ($order->sale_prc ?? 0)));
            $saleSum = max(0, (float) ($order->sale_sum ?? 0));

            $discPrc = round($base * $salePrc / 100, 2);
            $order->total_price_sale = max(0, round($base - $discPrc - $saleSum, 2));
        });
    }

    /** Удобный геттер: когда впервые/в последний раз был установлен данный статус */
    public function statusTime(OrderStatus|string $status): ?Carbon
    {
        $code = $status instanceof OrderStatus ? $status->value : $status;
        $ts = data_get($this->status_times, $code);

        return $ts ? Carbon::parse($ts) : null;
    }
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
