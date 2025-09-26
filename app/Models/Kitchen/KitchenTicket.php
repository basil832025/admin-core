<?php

namespace App\Models\Kitchen;

use App\Enums\OrderStatus;
use App\Models\Shop\Order;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class KitchenTicket extends Model
{
    protected $table = 'bs_kitchen_tickets';
    protected $fillable = [
        'order_id', 'stage', 'urgent', 'delivery_type',
        'processing_at', 'filling_at', 'molding_at', 'baking_at', 'prepared_at', // или prepared_at, если так в миграции
    ];

    protected $casts = [
        'urgent'        => 'bool',
        'processing_at' => 'datetime',
        'filling_at'    => 'datetime',
        'molding_at'    => 'datetime',
        'baking_at'     => 'datetime',
        'prepared_at'      => 'datetime',   // если в миграции prepared_at — поменяй здесь и в $fillable
        'stage'         => OrderStatus::class,   // ← главное: используем ТВОЙ OrderStatus
    ];
    public function items()
    {
        return $this->hasMany(\App\Models\Kitchen\KitchenTicketItem::class);
    }
    public function recalcStageFromItems(): void
    {
        if ($this->items()->count() === 0) return;

        // если все на Molding — можно поднять тикет до Molding, и т.д.
        $allMolding  = $this->items()->whereNotIn('stage', [OrderStatus::Molding, OrderStatus::Baking, OrderStatus::Prepared])->doesntExist();
        $allBaking   = $this->items()->whereNotIn('stage', [OrderStatus::Baking, OrderStatus::Prepared])->doesntExist();
        $allPrepared = $this->items()->where('stage', '!=', OrderStatus::Prepared->value)->doesntExist();

    if     ($allPrepared) $this->moveTo(OrderStatus::Prepared, auth()->id());
    elseif ($allBaking)   $this->moveTo(OrderStatus::Baking,   auth()->id());
    elseif ($allMolding)  $this->moveTo(OrderStatus::Molding,  auth()->id());
}
    /** Синхронизирует позиции из заказа в kitchen_ticket_items */
    public function syncItemsFromOrder(): void
    {
        $order = $this->order;
        if (!$order) return;

        foreach ($order->items as $it) {
            $this->items()->updateOrCreate(
                ['order_item_id' => $it->id],
                [
                    'product_name' => $it->product?->name ?? $it->product?->title,
                    'product_short'=> $it->product?->short_name,
                    'qty'          => $it->qty,
                ]
            );
        }
    }
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(KitchenTicketEvent::class);
    }

    /** Текущие (в работе) */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereIn('stage', [
            OrderStatus::Processing->value,
            OrderStatus::Filling->value,
            OrderStatus::Molding->value,
            OrderStatus::Baking->value,
        ]);
    }

    /** Архив (Приготовлен) */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('stage', OrderStatus::Prepared->value);
    }

    /** Удобный флаг */
    public function isArchived(): Attribute
    {
        return Attribute::get(fn () => $this->stage === OrderStatus::Prepared);
    }

    /** Переход по стадиям */
    public function moveTo(OrderStatus $toStage, ?int $byUserId = null, array $meta = []): void
    {
        $from = $this->stage;
        $this->stage = $toStage;

        $now = now();
        match ($toStage) {
            OrderStatus::Processing => $this->processing_at ??= $now,
            OrderStatus::Filling    => $this->filling_at    ??= $now,
            OrderStatus::Molding    => $this->molding_at    ??= $now,
            OrderStatus::Baking     => $this->baking_at     ??= $now,
            OrderStatus::Prepared   => $this->prepared_at      ??= $now, // или prepared_at
            default => null,
        };

        $this->save();

        $this->events()->create([
            'from_stage' => $from?->value,
            'to_stage'   => $toStage->value,
            'user_id'    => $byUserId,
            'meta'       => $meta,
        ]);
    }
}
