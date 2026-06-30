<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaypartsTransaction extends Model
{
    protected $table = 'bs_payparts_transactions';

    protected $fillable = [
        'shop_order_id',
        'payparts_bank_id',
        'status',
        'merchant_type',
        'parts_count',
        'amount',
        'order_id',
        'token',
        'signature',
        'request_payload',
        'response_payload',
        'response_message',
        'response_code',
        'redirect_url',
        'response_url',
        'customer_phone',
        'customer_email',
        'customer_locale',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'amount' => 'decimal:2',
        'parts_count' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'shop_order_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(PaypartsBank::class, 'payparts_bank_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaypartsRefund::class, 'payparts_transaction_id');
    }
}
