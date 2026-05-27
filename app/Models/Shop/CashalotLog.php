<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashalotLog extends Model
{
    protected $table = 'bs_cashalot_logs';

    protected $fillable = [
        'shop_order_id',
        'liqpay_log_id',
        'status',
        'error_code',
        'error_message',
        'num_fiscal',
        'receipt_url',
        'check_sum',
        'payment_type',
        'consumer_service_type',
        'consumer_phone',
        'consumer_status',
        'consumer_error_code',
        'consumer_error_message',
        'request_payload',
        'response_payload',
        'consumer_response_payload',
        'fiscalized_at',
        'sent_to_consumer_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'consumer_response_payload' => 'array',
        'check_sum' => 'decimal:2',
        'fiscalized_at' => 'datetime',
        'sent_to_consumer_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'shop_order_id');
    }

    public function liqpayLog(): BelongsTo
    {
        return $this->belongsTo(LiqPayLog::class, 'liqpay_log_id');
    }
}
