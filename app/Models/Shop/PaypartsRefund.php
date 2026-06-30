<?php

namespace App\Models\Shop;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaypartsRefund extends Model
{
    protected $table = 'bs_payparts_refunds';

    protected $fillable = [
        'shop_order_id',
        'payparts_transaction_id',
        'payparts_bank_id',
        'initiated_by_user_id',
        'status',
        'amount',
        'order_id',
        'decline_request_payload',
        'decline_response_payload',
        'state_request_payload',
        'state_response_payload',
        'response_message',
        'checked_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'decline_request_payload' => 'array',
        'decline_response_payload' => 'array',
        'state_request_payload' => 'array',
        'state_response_payload' => 'array',
        'checked_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'shop_order_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaypartsTransaction::class, 'payparts_transaction_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(PaypartsBank::class, 'payparts_bank_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }
}
