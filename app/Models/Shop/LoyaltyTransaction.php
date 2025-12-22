<?php

namespace App\Models\Shop;

use App\Models\Shop\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    use HasFactory;

    protected $table = 'bs_loyalty_transactions';

    // Типы транзакций как константы — удобно использовать в сервисе
    public const TYPE_ACCRUAL    = 'accrual';
    public const TYPE_SPEND      = 'spend';
    public const TYPE_EXPIRE     = 'expire';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_REVERSE    = 'reverse';

    protected $fillable = [
        'account_id',
        'order_id',
        'type',
        'source',
        'amount',
        'remaining_amount',
        'expires_at',
        'meta',
        'balance_after',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'balance_after'    => 'decimal:2',
        'expires_at'       => 'datetime',
        'meta'             => 'array',
    ];

    /**
     * Бонусный счёт, к которому относится транзакция.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'account_id');
    }

    /**
     * Заказ, с которым связана операция (если есть).
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
