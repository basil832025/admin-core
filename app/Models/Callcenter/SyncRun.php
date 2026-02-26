<?php

namespace App\Models\Callcenter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRun extends Model
{
    protected $table = 'bs_cc_sync_runs';

    protected $fillable = [
        'source_id',
        'type',
        'status',
        'processed',
        'created',
        'updated',
        'failed',
        'message',
        'meta',
    ];

    protected $casts = [
        'processed' => 'integer',
        'created' => 'integer',
        'updated' => 'integer',
        'failed' => 'integer',
        'meta' => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }
}
