<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyAccount extends Model
{
    use HasFactory;

    protected $table = 'bs_loyalty_accounts';

    protected $fillable = [
        'client_id',
        'phone',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    /**
     * Клиент, к которому привязан счёт.
     *
     * ВАЖНО: если у тебя другая модель клиента (не App\Models\Client),
     * измени класс здесь.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Транзакции по бонусному счёту.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class, 'account_id');
    }
}
