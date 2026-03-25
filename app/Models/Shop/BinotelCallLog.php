<?php

namespace App\Models\Shop;

use App\Models\Callcenter\Source;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinotelCallLog extends Model
{
    protected $table = 'bs_cc_binotel_call_logs';

    protected $fillable = [
        'event_type',
        'status',
        'request_type',
        'call_type',
        'caller_phone',
        'client_id',
        'client_name',
        'pbx_number',
        'pbx_name',
        'source_id',
        'source_name',
        'point_name',
        'internal_number',
        'company_id',
        'general_call_id',
        'crm_url',
        'ip',
        'request_payload',
    ];

    protected $casts = [
        'request_payload' => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
