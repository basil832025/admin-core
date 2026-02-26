<?php

namespace App\Models\Callcenter;

use App\Models\Shop\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceClient extends Model
{
    protected $table = 'bs_cc_source_clients';

    protected $fillable = [
        'source_id',
        'external_id',
        'external_phone',
        'name',
        'email',
        'local_client_id',
        'payload',
        'synced_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function localClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'local_client_id');
    }
}
