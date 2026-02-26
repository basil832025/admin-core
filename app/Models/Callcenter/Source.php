<?php

namespace App\Models\Callcenter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    protected $table = 'bs_cc_sources';

    protected $fillable = [
        'name',
        'slug',
        'base_url',
        'api_key',
        'is_active',
        'sync_enabled',
        'last_catalog_synced_at',
        'last_orders_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sync_enabled' => 'boolean',
        'last_catalog_synced_at' => 'datetime',
        'last_orders_synced_at' => 'datetime',
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(SourceCategory::class, 'source_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(SourceProduct::class, 'source_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(SourceClient::class, 'source_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SourceOrder::class, 'source_id');
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class, 'source_id');
    }
}
