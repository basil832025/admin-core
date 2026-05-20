<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    protected $table = 'bs_sms_logs';

    protected $fillable = [
        'client_id',
        'channel',
        'message_type',
        'phone',
        'normalized_phone',
        'sender',
        'provider_request_id',
        'provider_status',
        'delivery_status',
        'http_status',
        'success',
        'error_message',
        'message_preview',
        'message_text',
        'context',
        'provider_payload',
        'provider_response',
        'delivery_checked_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'context' => 'array',
        'provider_payload' => 'array',
        'provider_response' => 'array',
        'delivery_checked_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
