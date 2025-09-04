<?php
namespace App\Models\Kitchen;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenTicketItem extends Model
{
protected $fillable = [
'kitchen_ticket_id', 'order_item_id',
'product_name', 'product_short', 'qty',
'stage', 'filling_at', 'molding_at', 'baking_at', 'prepared_at',
];

protected $casts = [
'stage'       => OrderStatus::class,
'filling_at'  => 'datetime',
'molding_at'  => 'datetime',
'baking_at'   => 'datetime',
'prepared_at' => 'datetime',
];

public function ticket(): BelongsTo
{
return $this->belongsTo(KitchenTicket::class, 'kitchen_ticket_id');
}

public function moveTo(OrderStatus $to, ?int $byUserId = null): void
{
$now = now();
match ($to) {
OrderStatus::Filling  => $this->filling_at  ??= $now,
OrderStatus::Molding  => $this->molding_at  ??= $now,
OrderStatus::Baking   => $this->baking_at   ??= $now,
OrderStatus::Prepared => $this->prepared_at ??= $now,
default => null,
};
$this->stage = $to;
$this->save();
}
}
